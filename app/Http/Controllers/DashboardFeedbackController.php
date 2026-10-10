<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardFeedbackController extends Controller
{
    private const SCORE_MAX = 5;

    public function index(): View
    {
        $ratings = $this->experienceRatings();
        $surveys = $this->surveyResponses();

        return view('dashboard-feedback', [
            'scoreMax' => self::SCORE_MAX,
            'experience' => $this->experienceSummary($ratings),
            'survey' => $this->surveySummary($surveys),
            'ratings' => $ratings,
            'surveys' => $surveys,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function experienceRatings(): array
    {
        return DB::table('user_experience_ratings as ratings')
            ->join('users as users', 'users.user_id', '=', 'ratings.user_id')
            ->leftJoin('reservations as reservations', 'reservations.reservation_id', '=', 'ratings.reservation_id')
            ->orderByDesc('ratings.created_at')
            ->limit(100)
            ->get([
                'ratings.rating_id',
                'ratings.reservation_id',
                'ratings.rating',
                'ratings.created_at',
                'reservations.activity_name',
                'users.first_name',
                'users.middle_initial',
                'users.last_name',
                'users.suffix',
                'users.full_name',
                'users.username',
            ])
            ->map(function ($row): array {
                $when = $this->formatWhenParts($row->created_at);

                return [
                    'id' => (int) $row->rating_id,
                    'reservation_id' => (int) $row->reservation_id,
                    'activity' => trim((string) ($row->activity_name ?? '')) ?: 'Reservation',
                    'respondent' => $this->displayName($row),
                    'score' => (int) $row->rating,
                    'submitted_date' => $when['date'],
                    'submitted_time' => $when['time'],
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function surveyResponses(): array
    {
        return DB::table('user_feedback as feedback')
            ->join('users as users', 'users.user_id', '=', 'feedback.user_id')
            ->orderByDesc('feedback.created_at')
            ->limit(100)
            ->get([
                'feedback.feedback_id',
                'feedback.navigation_satisfaction',
                'feedback.reservation_satisfaction',
                'feedback.responsiveness_satisfaction',
                'feedback.information_satisfaction',
                'feedback.additional_comments',
                'feedback.created_at',
                'users.first_name',
                'users.middle_initial',
                'users.last_name',
                'users.suffix',
                'users.full_name',
                'users.username',
            ])
            ->map(function ($row): array {
                $scores = [
                    'navigation' => (int) $row->navigation_satisfaction,
                    'reservation' => (int) $row->reservation_satisfaction,
                    'responsiveness' => (int) $row->responsiveness_satisfaction,
                    'information' => (int) $row->information_satisfaction,
                ];
                $when = $this->formatWhenParts($row->created_at);

                return [
                    'id' => (int) $row->feedback_id,
                    'respondent' => $this->displayName($row),
                    'scores' => $scores,
                    'average' => round(array_sum($scores) / count($scores), 1),
                    'comment' => trim((string) ($row->additional_comments ?? '')),
                    'submitted_date' => $when['date'],
                    'submitted_time' => $when['time'],
                ];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $ratings
     * @return array<string, mixed>
     */
    private function experienceSummary(array $ratings): array
    {
        $scores = array_column($ratings, 'score');
        $distribution = array_fill(1, self::SCORE_MAX, 0);
        foreach ($scores as $score) {
            $distribution[(int) $score] = ($distribution[(int) $score] ?? 0) + 1;
        }

        return [
            'count' => count($scores),
            'average' => $scores === [] ? null : round(array_sum($scores) / count($scores), 1),
            'distribution' => $distribution,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $surveys
     * @return array<string, mixed>
     */
    private function surveySummary(array $surveys): array
    {
        $questions = [
            'navigation' => 'Finding your way around',
            'reservation' => 'Making a reservation',
            'responsiveness' => 'How quickly offices respond',
            'information' => 'Clarity of information',
        ];
        $averages = [];

        foreach ($questions as $key => $label) {
            $scores = array_map(static fn (array $survey): int => (int) $survey['scores'][$key], $surveys);
            $averages[$key] = [
                'label' => $label,
                'average' => $scores === [] ? null : round(array_sum($scores) / count($scores), 1),
            ];
        }

        $overallScores = array_column($surveys, 'average');

        return [
            'count' => count($surveys),
            'average' => $overallScores === [] ? null : round(array_sum($overallScores) / count($overallScores), 1),
            'questions' => $averages,
        ];
    }

    private function displayName(object $row): string
    {
        $middle = trim((string) ($row->middle_initial ?? ''));
        $parts = array_filter([
            trim((string) ($row->first_name ?? '')),
            $middle !== '' ? rtrim($middle, '.') . '.' : '',
            trim((string) ($row->last_name ?? '')),
            trim((string) ($row->suffix ?? '')),
        ], static fn (string $part): bool => $part !== '');

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        $fullName = trim((string) ($row->full_name ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string) ($row->username ?? ''));

        return $username !== '' ? $username : 'User';
    }

    /**
     * @return array{date: string, time: string}
     */
    private function formatWhenParts(mixed $timestamp): array
    {
        if ($timestamp === null || $timestamp === '') {
            return ['date' => '', 'time' => ''];
        }

        $when = Carbon::parse($timestamp)->timezone('Asia/Manila');

        return [
            'date' => $when->format('M j, Y'),
            'time' => $when->format('g:i A'),
        ];
    }
}
