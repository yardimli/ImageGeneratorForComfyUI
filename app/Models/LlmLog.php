<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

	class LlmLog extends Model
	{
		use HasFactory;

		/**
		 * The table associated with the model.
		 *
		 * @var string
		 */
		protected $table = 'llm_logs';

		/**
		 * The attributes that are mass assignable.
		 *
		 * @var array<int, string>
		 */
		protected $fillable = [
			'user_id',
			'timestamp',
			'reason',
			'model_id',
			'prompt_tokens',
			'completion_tokens',
		];

		/**
		 * The attributes that should be cast.
		 *
		 * @var array<string, string>
		 */
		protected $casts = [
			'timestamp' => 'datetime',
		];

		/**
		 * Get the user that made the LLM call.
		 */
		public function user()
		{
			return $this->belongsTo(User::class);
		}
	}
