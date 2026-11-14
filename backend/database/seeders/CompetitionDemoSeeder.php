<?php

namespace Database\Seeders;

use App\Models\Contestant;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Round;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompetitionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@tabul8.local'],
            [
                'name' => 'Chief Director',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'tabulator@tabul8.local'],
            [
                'name' => 'Head Tabulator',
                'password' => Hash::make('password'),
                'role' => 'tabulator',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'mc@tabul8.local'],
            [
                'name' => 'Stage Director / MC',
                'password' => Hash::make('password'),
                'role' => 'mc_stage',
                'is_active' => true,
            ]
        );

        $event = Event::firstOrCreate(
            ['title' => 'Miss Grand Archipelago 2026'],
            [
                'subtitle' => 'National Coronation Night · 15 Candidates',
                'event_type' => 'pageant',
                'status' => 'live',
                'led_mode' => 'on_stage',
                'revealed_contestant_ids' => [],
                'settings' => [
                    'auto_lock_on_cutoff' => false,
                    'default_deduction_presets' => [
                        ['label' => 'Time Overtime (-1.0)', 'points' => 1.0],
                        ['label' => 'Backstage Conduct (-2.0)', 'points' => 2.0],
                        ['label' => 'Late Call Time (-0.5)', 'points' => 0.5],
                    ],
                ],
                'created_by' => $superAdmin->id,
            ]
        );

        if ($event->rounds()->count() === 0) {
            $r1 = Round::create([
                'event_id' => $event->id,
                'name' => 'Round 1: Swimwear & Runway Preliminaries',
                'sequence' => 1,
                'scoring_strategy' => 'weighted',
                'weight_in_overall' => 50.00,
                'is_back_to_zero' => true,
                'cutoff_mode' => 'top_n_advance',
                'advancement_count' => 10,
                'eliminate_count' => 5,
                'status' => 'active',
            ]);

            $r2 = Round::create([
                'event_id' => $event->id,
                'name' => 'Round 2: Evening Gown & Advocacy',
                'sequence' => 2,
                'scoring_strategy' => 'weighted',
                'weight_in_overall' => 50.00,
                'is_back_to_zero' => false,
                'cutoff_mode' => 'top_n_advance',
                'advancement_count' => 5,
                'eliminate_count' => 5,
                'status' => 'upcoming',
            ]);

            $r3 = Round::create([
                'event_id' => $event->id,
                'name' => 'Round 3: Grand Finals Q&A',
                'sequence' => 3,
                'scoring_strategy' => 'ordinal',
                'weight_in_overall' => 100.00,
                'is_back_to_zero' => true,
                'cutoff_mode' => 'top_n_advance',
                'advancement_count' => 3,
                'eliminate_count' => 2,
                'status' => 'upcoming',
            ]);

            $event->update(['active_round_id' => $r1->id]);

            foreach ([
                [$r1, 'Stage Presence & Walk', 40.00, 1],
                [$r1, 'Poise & Physique', 35.00, 2],
                [$r1, 'Projection & Confidence', 25.00, 3],
                [$r2, 'Evening Gown Elegance', 50.00, 1],
                [$r2, 'Advocacy Articulation', 50.00, 2],
                [$r3, 'Intelligence & Relevance', 60.00, 1],
                [$r3, 'Composure Under Pressure', 40.00, 2],
            ] as [$round, $name, $weight, $seq]) {
                Criterion::create([
                    'round_id' => $round->id,
                    'name' => $name,
                    'weight_percentage' => $weight,
                    'min_score' => 70.00,
                    'max_score' => 100.00,
                    'score_step' => 0.50,
                    'sequence' => $seq,
                ]);
            }
        }

        $judges = [
            ['number' => 1, 'name' => 'Hon. Elena Reyes', 'role' => 'Panel Chairman', 'pin' => '104928', 'chair' => true],
            ['number' => 2, 'name' => 'Marcus Lim', 'role' => 'Creative Director', 'pin' => '218493', 'chair' => false],
            ['number' => 3, 'name' => 'Dr. Sofia Tan', 'role' => 'Civic Leader', 'pin' => '339104', 'chair' => false],
            ['number' => 4, 'name' => 'Julian Vance', 'role' => 'Runway Choreographer', 'pin' => '482910', 'chair' => false],
            ['number' => 5, 'name' => 'Camille Bautista', 'role' => 'Broadcast Host', 'pin' => '591024', 'chair' => false],
        ];

        foreach ($judges as $j) {
            Judge::firstOrCreate(
                ['event_id' => $event->id, 'judge_number' => $j['number']],
                [
                    'name' => $j['name'],
                    'role_title' => $j['role'],
                    'access_pin_hash' => Hash::make($j['pin']),
                    'plain_pin_reference' => $j['pin'],
                    'is_chairman' => $j['chair'],
                    'is_active' => true,
                ]
            );
        }

        $candidates = [
            ['Ariana Vance', 'Manila'],
            ['Beatrice Solis', 'Cebu'],
            ['Clarissa Mendoza', 'Davao'],
            ['daniela Villanueva', 'Iloilo'],
            ['Estelle Navarro', 'Baguio'],
            ['Francesca Cruz', 'Makati'],
            ['Gabriella Santos', 'Quezon City'],
            ['Helena Domingo', 'Cagayan de Oro'],
            ['Isabella Torres', 'Pampanga'],
            ['Jillian Morales', 'Palawan'],
            ['Katrina Pascual', 'Batangas'],
            ['Larissa Bautista', 'Laguna'],
            ['Marisol Mercado', 'Bacolod'],
            ['Nadia Pangilinan', 'Zamboanga'],
            ['Olivia Castaneda', 'Tagaytay'],
        ];

        $firstContestantId = null;
        foreach ($candidates as $idx => [$name, $origin]) {
            $contestant = Contestant::firstOrCreate(
                ['event_id' => $event->id, 'candidate_number' => $idx + 1, 'category' => 'Solo'],
                [
                    'name' => ucwords($name),
                    'origin_or_group' => $origin,
                    'asset_ratio' => 'PORTRAIT_4:5',
                    'status' => 'active',
                ]
            );
            $firstContestantId ??= $contestant->id;
        }

        if ($event->on_stage_contestant_id === null && $firstContestantId !== null) {
            $event->update(['on_stage_contestant_id' => $firstContestantId]);
        }
    }
}
