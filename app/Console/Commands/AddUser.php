<?php

	namespace App\Console\Commands;

	use Illuminate\Console\Command;
	use App\Models\User; // In Laravel 8+ ensure correct namespace; older versions use App\User.
	use Illuminate\Support\Facades\Hash;

	class AddUser extends Command
	{
		/**
		 * The name and signature of the console command.
		 *
		 * You can include options here as needed.
		 *
		 * @var string
		 */
		protected $signature = 'user:add';

		/**
		 * The console command description.
		 *
		 * @var string
		 */
		protected $description = 'Add a new user to the application';

		/**
		 * Execute the console command.
		 *
		 * @return int
		 */
		public function handle()
		{
			// Ask for user details
			$name = $this->ask('Enter the user name');
			$email = $this->ask('Enter the user email');

			// Ask for a password (hidden input)
			$password = $this->secret('Enter the password');

			// Confirm password
			$passwordConfirm = $this->secret('Confirm the password');
			if ($password !== $passwordConfirm) {
				$this->error('Passwords do not match.');
				return 1;
			}

			// Validate that a user with this email does not already exist
			if (User::where('email', $email)->exists()) {
				$this->error('A user with this email already exists!');
				return 1;
			}

			// Create the user
			User::create([
				'name' => $name,
				'email' => $email,
				'password' => Hash::make($password)
			]);

			$this->info("User {$name} ({$email}) created successfully.");
			return 0;
		}
	}
