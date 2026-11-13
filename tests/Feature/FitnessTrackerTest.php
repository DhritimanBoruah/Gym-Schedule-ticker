<?php

namespace Tests\Feature;

use Tests\TestCase;

class FitnessTrackerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->useStoragePath(sys_get_temp_dir().'/fitness-tests-'.uniqid());
    }

    public function test_schedule_endpoint_returns_the_configured_six_day_split_and_rest_day(): void
    {
        $response = $this->getJson('/api/fitness')->assertOk();
        $schedule = $response->json('schedule');

        $this->assertCount(7, $schedule);
        $this->assertSame('Push + Core', $schedule[0]['title']);
        $this->assertSame('Bench Press', $schedule[0]['exercises'][1]['name']);
        $this->assertSame(3, $schedule[0]['exercises'][1]['sets']);
        $this->assertSame('Rest & Recovery', $schedule[6]['title']);
        $this->assertSame([], $schedule[6]['exercises']);
    }

    public function test_daily_log_is_upserted_by_date_and_rejects_invalid_sets(): void
    {
        $log = [
            'date' => '2026-11-16', 'day' => 'Monday', 'workout_name' => 'Push + Core',
            'status' => 'in_progress', 'tasks' => ['warmup' => false, 'mobility' => false, 'strength' => false, 'cardio' => false, 'cooldown' => false],
            'exercises' => [[
                'exercise_id' => 'bench-press', 'exercise_name' => 'Bench Press', 'target_sets' => 1, 'target_reps' => '8-12', 'note' => '',
                'sets' => [['set_number' => 1, 'target_reps' => '8-12', 'actual_reps' => 8, 'weight_kg' => 20, 'duration_seconds' => '', 'completed' => true]],
            ]],
        ];

        $this->postJson('/api/logs', $log)->assertOk();
        $log['exercises'][0]['sets'][0]['actual_reps'] = 10;
        $this->postJson('/api/logs', $log)->assertOk();

        $this->assertCount(1, $this->getJson('/api/fitness')->json('logs'));
        $this->assertSame(10, $this->getJson('/api/fitness')->json('logs.0.exercises.0.sets.0.actual_reps'));

        $log['exercises'][0]['sets'][0]['weight_kg'] = -5;
        $this->postJson('/api/logs', $log)->assertUnprocessable();
    }
}
