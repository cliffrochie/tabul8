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

final class DemoEventSeeder extends Seeder
{
    public function run(): void
    {
        $tabulator = User::updateOrCreate(
            ['email' => 'tabulator@tabul8.local'],
            [
                'name' => 'Chief Tabulator',
                'password' => Hash::make('password'),
                'role' => 'tabulator',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@tabul8.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        $event = Event::firstOrCreate(
            ['id' => 1],
            [
                'title' => 'Miss & Mr. University Grand Finals 2026',
                'subtitle' => 'Live Arena Tabulation',
                'event_type' => 'pageant',
                'status' => 'live',
                'led_mode' => 'on_stage',
                'revealed_contestant_ids' => [],
                'settings' => [],
                'created_by' => $tabulator->id,
            ]
        );

        $round1 = Round::updateOrCreate(
            ['event_id' => $event->id, 'sequence' => 1],
            [
                'name' => 'Preliminary — Evening Gown & Formal Wear',
                'scoring_strategy' => 'weighted',
                'weight_in_overall' => 50.00,
                'is_back_to_zero' => false,
                'advancement_count' => 3,
                'status' => 'active',
                'is_locked' => false,
                'settings' => [],
            ]
        );

        $round1Criteria = [
            [
                'sequence' => 1,
                'name' => 'Poise & Stage Presence',
                'description' => 'Confidence, posture, and command of the stage.',
                'weight_percentage' => 35.00,
                'min_score' => 70.00,
                'max_score' => 100.00,
                'score_step' => 0.50,
            ],
            [
                'sequence' => 2,
                'name' => 'Attire & Elegance',
                'description' => 'Fit, styling, and overall presentation.',
                'weight_percentage' => 35.00,
                'min_score' => 70.00,
                'max_score' => 100.00,
                'score_step' => 0.50,
            ],
            [
                'sequence' => 3,
                'name' => 'Audience Impact',
                'description' => 'Charisma and connection with the crowd.',
                'weight_percentage' => 30.00,
                'min_score' => 70.00,
                'max_score' => 100.00,
                'score_step' => 0.50,
            ],
        ];

        foreach ($round1Criteria as $criterion) {
            Criterion::updateOrCreate(
                ['round_id' => $round1->id, 'sequence' => $criterion['sequence']],
                $criterion
            );
        }

        $round2 = Round::updateOrCreate(
            ['event_id' => $event->id, 'sequence' => 2],
            [
                'name' => 'Final Q&A',
                'scoring_strategy' => 'weighted',
                'weight_in_overall' => 50.00,
                'is_back_to_zero' => true,
                'advancement_count' => 3,
                'status' => 'upcoming',
                'is_locked' => false,
                'settings' => [],
            ]
        );

        $round2Criteria = [
            [
                'sequence' => 1,
                'name' => 'Intelligence & Articulation',
                'description' => 'Clarity, depth, and relevance of response.',
                'weight_percentage' => 60.00,
                'min_score' => 70.00,
                'max_score' => 100.00,
                'score_step' => 0.50,
            ],
            [
                'sequence' => 2,
                'name' => 'Composure & Confidence',
                'description' => 'Delivery under pressure.',
                'weight_percentage' => 40.00,
                'min_score' => 70.00,
                'max_score' => 100.00,
                'score_step' => 0.50,
            ],
        ];

        foreach ($round2Criteria as $criterion) {
            Criterion::updateOrCreate(
                ['round_id' => $round2->id, 'sequence' => $criterion['sequence']],
                $criterion
            );
        }

        $contestantsData = [
            ['candidate_number' => 1, 'name' => 'Aria Mendoza', 'origin_or_group' => 'College of Engineering', 'performance_meta' => 'Evening Gown · Emerald Silk'],
            ['candidate_number' => 2, 'name' => 'Rafael Santos', 'origin_or_group' => 'College of Business', 'performance_meta' => 'Formal Barong · Ivory'],
            ['candidate_number' => 3, 'name' => 'Camille Navarro', 'origin_or_group' => 'College of Arts & Sciences', 'performance_meta' => 'Evening Gown · Midnight Velvet'],
            ['candidate_number' => 4, 'name' => 'Liam Villanueva', 'origin_or_group' => 'College of Architecture', 'performance_meta' => 'Tailored Tuxedo · Charcoal'],
            ['candidate_number' => 5, 'name' => 'Bianca Cruz', 'origin_or_group' => 'College of Nursing', 'performance_meta' => 'Evening Gown · Champagne'],
        ];

        $firstContestant = null;
        foreach ($contestantsData as $index => $data) {
            $contestant = Contestant::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'candidate_number' => $data['candidate_number'],
                    'category' => 'Solo',
                ],
                [
                    'name' => $data['name'],
                    'origin_or_group' => $data['origin_or_group'],
                    'performance_meta' => $data['performance_meta'],
                    'asset_ratio' => 'PORTRAIT_4:5',
                    'status' => 'active',
                ]
            );

            $round1->contestants()->syncWithoutDetaching([
                $contestant->id => ['performance_order' => $index + 1],
            ]);

            if ($firstContestant === null) {
                $firstContestant = $contestant;
            }
        }

        $judgesData = [
            ['judge_number' => 1, 'name' => 'Elena Vance', 'role_title' => 'Chairman of the Board', 'pin' => '111111', 'is_chairman' => true],
            ['judge_number' => 2, 'name' => 'Marcus Chen', 'role_title' => 'Senior Adjudicator', 'pin' => '222222', 'is_chairman' => false],
            ['judge_number' => 3, 'name' => 'Sofia Reyes', 'role_title' => 'Guest Adjudicator', 'pin' => '333333', 'is_chairman' => false],
        ];

        foreach ($judgesData as $judge) {
            Judge::updateOrCreate(
                ['event_id' => $event->id, 'judge_number' => $judge['judge_number']],
                [
                    'name' => $judge['name'],
                    'role_title' => $judge['role_title'],
                    'access_pin_hash' => Hash::make($judge['pin']),
                    'plain_pin_reference' => $judge['pin'],
                    'is_chairman' => $judge['is_chairman'],
                    'is_active' => true,
                ]
            );
        }

        $event->update([
            'active_round_id' => $round1->id,
            'on_stage_contestant_id' => $firstContestant?->id,
        ]);
    }
}
