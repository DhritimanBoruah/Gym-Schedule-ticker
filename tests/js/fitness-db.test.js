import test from 'node:test';
import assert from 'node:assert/strict';
import { validateBackup } from '../../resources/js/fitness-db.js';

const emptyBackup = () => ({ app:'GymTracker', version:1, profile:null, workouts:[], exercise_logs:[], weight_history:[], settings:[] });

test('accepts an empty version 1 backup', () => {
    assert.doesNotThrow(() => validateBackup(emptyBackup()));
});

test('rejects unsupported or malformed backup documents', () => {
    assert.throws(() => validateBackup({ ...emptyBackup(), version:99 }), /supported/);
    assert.throws(() => validateBackup('{bad json'), /supported/);
});

test('rejects impossible dates and duplicate daily workout records', () => {
    const invalidDate=emptyBackup();
    invalidDate.workouts=[{id:'w1',date:'2026-02-31',day:'Tuesday',workout_name:'Pull',status:'in_progress'}];
    assert.throws(() => validateBackup(invalidDate), /workout/);
    const duplicate=emptyBackup();
    duplicate.workouts=[
        {id:'w1',date:'2026-11-13',day:'Friday',workout_name:'Legs',status:'in_progress'},
        {id:'w2',date:'2026-11-13',day:'Friday',workout_name:'Legs',status:'completed'},
    ];
    assert.throws(() => validateBackup(duplicate), /duplicate/);
});

test('rejects exercise records detached from a workout', () => {
    const backup=emptyBackup();
    backup.exercise_logs=[{id:'e1',workout_id:'missing',date:'2026-11-13',exercise_name:'Squat',sets:[]}];
    assert.throws(() => validateBackup(backup), /exercise/);
});
