<?php

namespace App\Http\Controllers;

use App\Services\FitnessStore;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FitnessController extends Controller
{
    public function __construct(private FitnessStore $store) {}

    public function index()
    {
        $profile = $this->store->read('profile', [
            'name' => '', 'age' => null, 'sex' => 'female', 'height_cm' => 167,
            'current_weight_kg' => 77, 'starting_weight_kg' => 77, 'target_weight_kg' => 68, 'weekly_training_days' => 6,
            'preferred_workout_duration_minutes' => 75, 'fitness_goal' => 'fat_loss_and_calisthenics',
            'activity_level' => 1.45, 'protein_g_per_kg' => 1.6, 'daily_steps_target' => 8000,
        ]);
        $schedule = $this->store->read('schedule', $this->schedule());
        if (! $this->store->read('schedule')) $this->store->write('schedule', $schedule);
        if (! $this->store->read('profile')) $this->store->write('profile', $profile);
        return response()->json(['profile' => $profile, 'schedule' => $schedule,
            'logs' => $this->store->read('logs'), 'weights' => $this->store->read('weights')]);
    }

    public function saveLog(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'], 'day' => ['required', 'string', 'max:20'],
            'workout_name' => ['required', 'string', 'max:100'], 'exercises' => ['required', 'array', 'max:30'],
            'exercises.*.exercise_id' => ['required', 'string', 'max:100'], 'exercises.*.exercise_name' => ['required', 'string', 'max:150'],
            'exercises.*.target_sets' => ['required', 'integer', 'between:1,20'], 'exercises.*.target_reps' => ['required', 'string', 'max:40'],
            'exercises.*.note' => ['nullable', 'string', 'max:1000'], 'exercises.*.sets' => ['required', 'array', 'max:20'],
            'exercises.*.sets.*.set_number' => ['required', 'integer', 'between:1,20'], 'exercises.*.sets.*.target_reps' => ['required', 'string', 'max:40'],
            'exercises.*.sets.*.actual_reps' => ['nullable', 'numeric', 'between:0,1000'], 'exercises.*.sets.*.weight_kg' => ['nullable', 'numeric', 'between:0,1000'],
            'exercises.*.sets.*.duration_seconds' => ['nullable', 'numeric', 'between:0,86400'], 'exercises.*.sets.*.completed' => ['required', 'boolean'],
            'tasks' => ['required', 'array'], 'tasks.warmup' => ['required', 'boolean'], 'tasks.mobility' => ['required', 'boolean'],
            'tasks.strength' => ['required', 'boolean'], 'tasks.cardio' => ['required', 'boolean'], 'tasks.cooldown' => ['required', 'boolean'],
            'status' => ['required', 'in:in_progress,completed'],
        ]);
        $logs = $this->store->read('logs');
        $found = false;
        foreach ($logs as &$log) {
            if ($log['date'] === $data['date']) {
                $data['id'] = $log['id'] ?? (string) Str::uuid();
                $log = array_merge($log, $data, ['updated_at' => now()->toIso8601String()]);
                $found = true; break;
            }
        }
        unset($log);
        if (! $found) $logs[] = array_merge($data, ['id' => (string) Str::uuid(), 'created_at' => now()->toIso8601String()]);
        $this->store->write('logs', $logs);
        return response()->json(['ok' => true]);
    }

    public function saveProfile(Request $request)
    {
        $profile = array_merge($this->store->read('profile'), $request->validate([
            'name' => ['nullable', 'string', 'max:100'], 'age' => ['nullable', 'integer', 'between:14,100'],
            'sex' => ['required', 'in:female,male'], 'height_cm' => ['nullable', 'numeric', 'between:100,250'],
            'current_weight_kg' => ['nullable', 'numeric', 'between:25,350'], 'starting_weight_kg' => ['nullable', 'numeric', 'between:25,350'], 'target_weight_kg' => ['nullable', 'numeric', 'between:25,350'],
            'weekly_training_days' => ['required', 'integer', 'between:1,7'], 'preferred_workout_duration_minutes' => ['required', 'integer', 'between:15,240'],
            'activity_level' => ['required', 'numeric', 'between:1.2,2.0'], 'protein_g_per_kg' => ['required', 'numeric', 'between:1,2.5'],
            'daily_steps_target' => ['required', 'integer', 'between:1000,30000'], 'fitness_goal' => ['required', 'string', 'max:80'],
        ]));
        $this->store->write('profile', $profile);
        return response()->json(['ok' => true, 'profile' => $profile]);
    }

    public function saveWeight(Request $request)
    {
        $entry = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'weight_kg' => ['required', 'numeric', 'between:25,350'], 'waist_cm' => ['nullable', 'numeric', 'between:30,250'], 'notes' => ['nullable', 'string', 'max:500']]);
        $weights = $this->store->read('weights');
        $existing = false;
        foreach ($weights as &$weight) {
            if ($weight['date'] === $entry['date']) {
                $entry['id'] = $weight['id'] ?? (string) Str::uuid();
                $weight = $entry;
                $existing = true;
                break;
            }
        }
        unset($weight);
        if (! $existing) $weights[] = array_merge($entry, ['id' => (string) Str::uuid()]);
        usort($weights, fn ($a, $b) => strcmp($a['date'], $b['date']));
        $this->store->write('weights', $weights);
        $profile = $this->store->read('profile'); $profile['current_weight_kg'] = $entry['weight_kg']; $this->store->write('profile', $profile);
        return response()->json(['ok' => true, 'weights' => $weights, 'profile' => $profile]);
    }

    private function schedule(): array
    {
        $days = [
            ['Monday','Push + Core','Chest, Shoulders, Triceps', [['Push-ups',3,'12–15'],['Bench Press',3,'8–12'],['Incline Dumbbell Press',3,'10–12'],['Shoulder Press',3,'10'],['Lateral Raises',3,'12–15'],['Triceps Cable Pushdown',3,'12'],['Plank',3,'40 sec'],['Hanging Knee Raises',3,'12']], '15 min incline treadmill walking'],
            ['Tuesday','Pull + Core','Back, Biceps, Grip Strength', [['Assisted Pull-ups',4,'6–10'],['Lat Pulldown',3,'10–12'],['Seated Cable Rows',3,'10–12'],['Single-arm Dumbbell Rows',3,'10'],['Face Pulls',3,'15'],['Dumbbell Biceps Curls',3,'12'],['Hammer Curls',2,'12'],['Dead Hang',3,'20–40 sec']], '15 min cycling'],
            ['Wednesday','Legs + Cardio','Quads, Hamstrings, Glutes', [['Bodyweight Squats',2,'15'],['Barbell Squats',3,'8–10'],['Romanian Deadlift',3,'10'],['Leg Press',3,'12'],['Leg Curls',3,'12'],['Standing Calf Raises',3,'15'],['Reverse Crunches',3,'15']], '20 min brisk walking'],
            ['Thursday','Upper Body Calisthenics','Bodyweight Strength and Control', [['Pull-ups / Assisted Pull-ups',4,'6–8'],['Dips / Assisted Dips',3,'6–10'],['Decline Push-ups',3,'10–12'],['Inverted Rows',3,'10–12'],['Pike Push-ups',3,'8–10'],['Diamond Push-ups',2,'8–12'],['Hanging Leg Raises / Knee Raises',3,'12'],['Hollow Body Hold',3,'20–30 sec']], '15 min easy cardio'],
            ['Friday','Legs + Conditioning','Leg Strength and Endurance', [['Bulgarian Split Squats',3,'10 each'],['Goblet Squats',3,'12'],['Hip Thrusts',3,'12'],['Leg Extensions',3,'12'],['Seated Leg Curls',3,'12'],['Calf Raises',3,'15'],['Mountain Climbers',3,'30 sec']], '15–20 min cycling or incline walking'],
            ['Saturday','Full Body + Calisthenics','Athletic Strength and Body Control', [['Pull-ups / Assisted Pull-ups',3,'6'],['Push-ups',3,'15'],['Dumbbell Thrusters',3,'10'],['Inverted Rows',3,'12'],['Walking Lunges',3,'12 each'],['Wall Handstand Hold',3,'20 sec'],['Hanging Knee Raises',3,'12'],['Burpees',2,'8']], '15 min moderate cardio'],
            ['Sunday','Rest & Recovery','Rest day', [], 'Light walking and mobility'],
        ];
        return array_map(fn ($d) => ['day' => $d[0], 'title' => $d[1], 'muscles' => $d[2], 'exercises' => array_map(fn ($e, $i) => ['id' => Str::slug($d[0].'-'.$e[0]), 'name' => $e[0], 'sets' => $e[1], 'reps' => $e[2], 'order' => $i + 1, 'timed' => str_contains($e[2], 'sec')], $d[3], array_keys($d[3])), 'finisher' => $d[4]], $days);
    }
}
