<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

// models
use App\Models\Setting;

// services
use App\Services\GoalService;

class EncounteredWord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'language',
        'stage',
        'word',
        'kanji',
        'reading',
        'base_word',
        'base_word_reading',
        'translation',
        'lookup_count',
        'read_count',
        'relearning',
        'created_at',
        'updated_at',
    ];

    public function setStage($stage, $ignoreAchivement = false) {
        // enforce saved words limit on transition to saved
        if ($this->stage >= 0 && $stage < 0) {
            $limit = \App\Models\User::where('id', $this->user_id)->value('saved_words_limit');
            if ($limit && $limit > 0) {
                $savedWords = EncounteredWord::where('user_id', $this->user_id)->where('stage', '<', 0)->count();
                $savedPhrases = \App\Models\Phrase::where('user_id', $this->user_id)->where('stage', '<', 0)->count();
                if (($savedWords + $savedPhrases) >= $limit) {
                    throw new \Exception('Saved words limit reached.');
                }
            }
        }

        // if it's a newly saved word, update today's achievement
        if ($this->stage >= 0 && $stage < 0 && !$ignoreAchivement) {
            (new GoalService())->updateGoalAchievement($this->user_id, $this->language, 'learn_words', 1);
            // award coins for learning a new word
            try { (new \App\Services\SocialService())->awardCoins($this->user_id, config('linguacafe.social.coins_per_word', 1)); } catch (\Throwable $e) {}
        }
        
        if ($this->stage >= 0 && $stage < 0 && $stage !== -7) {
            $this->relearning = true;
        }

        if ($stage >= 0) {
            $this->relearning = false;
        }

        $this->stage = $stage;
        $reviewIntervals = Setting::where('name', 'reviewIntervals')->first();
        $reviewIntervals = json_decode($reviewIntervals->value);

        // find the most optimal day for the next review
        if ($stage < 0) {
            $stageString = strval($stage);
            $possibleDates = $reviewIntervals->$stageString;
            $nextReviewIndex = 0;
            for ($i = 0; $i < count($possibleDates); $i++) {
                $data = new \stdClass();
                $data->date = Carbon::now()->addDays($possibleDates[$i])->toDateString();
                $data->count = EncounteredWord::where('user_id', $this->user_id)->where('next_review', $data->date)->count();
                $possibleDates[$i] = $data;

                if ($possibleDates[$i]->count < $possibleDates[$nextReviewIndex]->count) {
                    $nextReviewIndex = $i;
                }
            }
            
            $this->next_review = $possibleDates[$nextReviewIndex]->date;
            if (is_null($this->added_to_srs)) {
                $this->added_to_srs = Carbon::now()->toDateString();
            }
        } else {
            $this->next_review = null;
        }
    }
}
