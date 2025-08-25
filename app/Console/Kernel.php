<?php

	namespace App\Console;

	use Illuminate\Console\Scheduling\Schedule;
	use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

	class Kernel extends ConsoleKernel
	{
		/**
		 * Define the application's command schedule.
		 */
		protected function schedule(Schedule $schedule): void
		{
			// $schedule->command('inspire')->hourly();

			// MODIFICATION START: Register the render job command to run every minute.
			$schedule->command('app:process-render-jobs')
				->everyMinute() // This will run the command at the start of every minute.
				->withoutOverlapping(); // This prevents the command from running again if the previous run hasn't finished.
			// MODIFICATION END
		}

		/**
		 * Register the commands for the application.
		 */
		protected function commands(): void
		{
			$this->load(__DIR__.'/Commands');

			require base_path('routes/console.php');
		}
	}
