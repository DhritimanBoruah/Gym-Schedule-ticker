const DB_NAME = 'GymTrackerDB';
const DB_VERSION = 1;
const PROFILE_KEY = 'active-profile';
let connection;

function openDatabase() {
    if (!('indexedDB' in window)) return Promise.reject(new Error('IndexedDB is unavailable in this browser. Your fitness data cannot be saved here.'));
    if (connection) return connection;
    connection = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains('profile')) db.createObjectStore('profile', { keyPath: 'id' });
            if (!db.objectStoreNames.contains('workouts')) {
                const store = db.createObjectStore('workouts', { keyPath: 'id' });
                store.createIndex('date', 'date', { unique: true }); store.createIndex('status', 'status');
            }
            if (!db.objectStoreNames.contains('exercise_logs')) {
                const store = db.createObjectStore('exercise_logs', { keyPath: 'id' });
                store.createIndex('workout_id', 'workout_id'); store.createIndex('date', 'date'); store.createIndex('exercise_name', 'exercise_name');
            }
            if (!db.objectStoreNames.contains('weight_history')) {
                const store = db.createObjectStore('weight_history', { keyPath: 'id' });
                store.createIndex('date', 'date', { unique: true });
            }
            if (!db.objectStoreNames.contains('settings')) db.createObjectStore('settings', { keyPath: 'id' });
        };
        request.onsuccess = () => { const db=request.result; db.onversionchange=()=>{db.close();connection=null;}; resolve(db); };
        request.onerror = () => { connection=null; reject(request.error || new Error('Could not open local fitness storage.')); };
        request.onblocked = () => { connection=null; reject(new Error('Fitness storage upgrade is blocked. Close other tracker tabs and reload.')); };
    });
    return connection;
}

function transact(stores, mode, action) {
    return openDatabase().then(db => new Promise((resolve,reject) => {
        let result;
        const tx=db.transaction(stores,mode);
        tx.oncomplete=()=>resolve(result);
        tx.onerror=()=>reject(tx.error || new Error('Local fitness storage transaction failed.'));
        tx.onabort=()=>reject(tx.error || new Error('Local fitness storage transaction was cancelled.'));
        try { result=action(tx); } catch(error) { tx.abort(); reject(error); }
    }));
}
const requestValue = request => new Promise((resolve,reject)=>{request.onsuccess=()=>resolve(request.result);request.onerror=()=>reject(request.error);});
export const db = {
    open: openDatabase,
    add: (store,record) => transact([store],'readwrite',tx=>tx.objectStore(store).add(record)),
    put: (store,record) => transact([store],'readwrite',tx=>tx.objectStore(store).put(record)),
    get: (store,key) => openDatabase().then(database=>requestValue(database.transaction(store).objectStore(store).get(key))),
    all: store => openDatabase().then(database=>requestValue(database.transaction(store).objectStore(store).getAll())),
    delete: (store,key) => transact([store],'readwrite',tx=>tx.objectStore(store).delete(key)),
    byIndex: (store,index,key) => openDatabase().then(database=>requestValue(database.transaction(store).objectStore(store).index(index).getAll(key))),
    saveProfile(profile) { return this.put('profile',{...profile,id:PROFILE_KEY,updated_at:new Date().toISOString(),created_at:profile.created_at||new Date().toISOString()}); },
    async saveWorkout(log) {
        const exercises=log.exercises.map((exercise,index)=>({...exercise,id:`${log.id}:${exercise.exercise_id||index}`,workout_id:log.id,date:log.date}));
        return transact(['workouts','exercise_logs'],'readwrite',tx=>{
            const workouts=tx.objectStore('workouts'), exerciseStore=tx.objectStore('exercise_logs');
            const workout={...log,total_exercises:log.exercises.length,completed_exercises:log.exercises.filter(ex=>ex.sets.every(set=>set.completed)).length,start_time:log.start_time||new Date().toISOString(),completion_time:log.status==='completed'?(log.completion_time||new Date().toISOString()):null};
            delete workout.exercises; workouts.put(workout);
            const index=exerciseStore.index('workout_id'), cursor=index.openCursor(IDBKeyRange.only(log.id));
            cursor.onsuccess=()=>{const current=cursor.result;if(current){current.delete();current.continue();return;}exercises.forEach(exercise=>exerciseStore.put(exercise));};
        });
    },
    async loadWorkoutState() {
        const [workouts,exerciseLogs,weights,profile]=await Promise.all([this.all('workouts'),this.all('exercise_logs'),this.all('weight_history'),this.get('profile',PROFILE_KEY)]);
        const logs=workouts.map(workout=>({...workout,exercises:exerciseLogs.filter(ex=>ex.workout_id===workout.id).map(({id,workout_id,date,...exercise})=>exercise)}));
        return {profile:profile||null,logs,weights};
    },
    saveWeight(entry) { return this.put('weight_history',{...entry,id:`weight:${entry.date}`}); },
    async exportData() { const [profile,workouts,exercise_logs,weight_history,settings]=await Promise.all([this.all('profile'),this.all('workouts'),this.all('exercise_logs'),this.all('weight_history'),this.all('settings')]);let theme=null;try{theme=localStorage.getItem('fitness-theme');}catch{}if(theme)settings.push({id:'theme',value:theme});return {app:'GymTracker',version:1,exported_at:new Date().toISOString(),profile:profile.find(item=>item.id===PROFILE_KEY)||null,workouts,exercise_logs,weight_history,settings}; },
    async importData(data,mode='merge') {
        validateBackup(data);
        const stores=['profile','workouts','exercise_logs','weight_history','settings'];
        await transact(stores,'readwrite',tx=>{
            if(mode==='replace') stores.forEach(name=>tx.objectStore(name).clear());
            if(data.profile)tx.objectStore('profile').put({...data.profile,id:PROFILE_KEY});
            for(const [store,records] of [['workouts',data.workouts],['exercise_logs',data.exercise_logs],['weight_history',data.weight_history],['settings',data.settings]])records.forEach(record=>tx.objectStore(store).put(record));
        });
        const savedTheme=data.settings.find(item=>item?.id==='theme')?.value;
        if(savedTheme==='dark'||savedTheme==='light')try{localStorage.setItem('fitness-theme',savedTheme);}catch{}
    }
};

export function validateBackup(data) {
    if(!data||data.app!=='GymTracker'||data.version!==1||!Array.isArray(data.workouts)||!Array.isArray(data.exercise_logs)||!Array.isArray(data.weight_history)||!Array.isArray(data.settings))throw new Error('This file is not a supported Gym Tracker backup (version 1).');
    const validDate=value=>{if(typeof value!=='string'||!/^\d{4}-\d{2}-\d{2}$/.test(value))return false;const date=new Date(`${value}T00:00:00Z`);return !Number.isNaN(date.getTime())&&date.toISOString().slice(0,10)===value;};
    if(data.profile!==null&&(!data.profile||typeof data.profile!=='object'))throw new Error('The backup profile is invalid.');
    if(data.profile&&(['age','height_cm','current_weight_kg','starting_weight_kg','target_weight_kg'].some(key=>data.profile[key]!=null&&!Number.isFinite(Number(data.profile[key])))))throw new Error('The backup profile contains invalid measurements.');
    const unique=(records,key)=>new Set(records.map(item=>item[key])).size===records.length;
    if(!unique(data.workouts,'id')||!unique(data.workouts,'date')||data.workouts.some(x=>!x||typeof x.id!=='string'||!validDate(x.date)||typeof x.day!=='string'||typeof x.workout_name!=='string'||!['in_progress','completed'].includes(x.status)))throw new Error('The backup contains an invalid or duplicate workout record.');
    const workoutIds=new Set(data.workouts.map(x=>x.id));
    if(!unique(data.exercise_logs,'id')||data.exercise_logs.some(x=>!x||typeof x.id!=='string'||typeof x.workout_id!=='string'||!workoutIds.has(x.workout_id)||!validDate(x.date)||typeof x.exercise_name!=='string'||!Array.isArray(x.sets)||x.sets.some(set=>!set||typeof set.completed!=='boolean')))throw new Error('The backup contains an invalid exercise record.');
    if(!unique(data.weight_history,'id')||!unique(data.weight_history,'date')||data.weight_history.some(x=>!x||typeof x.id!=='string'||!validDate(x.date)||!Number.isFinite(Number(x.weight_kg))||Number(x.weight_kg)<25||Number(x.weight_kg)>350))throw new Error('The backup contains an invalid or duplicate weight record.');
    if(data.settings.some(x=>!x||typeof x.id!=='string'))throw new Error('The backup contains an invalid setting.');
}
