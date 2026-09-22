<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructionStepImage extends Model
{
    protected $fillable = ['instruction_step_id', 'image_path', 'sort_order'];

    public function instructionStep(): BelongsTo
    {
        return $this->belongsTo(InstructionStep::class);
    }

    public function imageUrl(): string
    {
        return url('/img/' . $this->image_path);
    }
}
