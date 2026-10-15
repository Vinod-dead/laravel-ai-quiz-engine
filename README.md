QuizForge
QuizForge is an AI-powered quiz application built to make creating and practicing quizzes easier. It combines a Laravel application with AI-assisted question generation and tools for validating quiz content.
Features
- AI-assisted question generation to help create quiz questions.
- Question processing and validation to support consistent quiz content.
- Duplicate prevention to help avoid repeated questions.
- Organized Laravel application with a maintainable project structure.
- Automated tests to check application behavior.
Available features and AI behavior may depend on your local configuration and model provider.

Tech Stack
- PHP
- Laravel 12
- PostgreSQL
- Laravel AI SDK
- Ollama
- PHPUnit / Laravel testing tools
Check the dependency and environment files in this repository for the exact versions and configuration used by your setup.
Requirements
Before installing, make sure you have the tools required by the project, including PHP, Composer, PostgreSQL, and any AI model service configured for your environment.
Installation
1. Open a terminal in the project directory.
2. Install PHP dependencies:
   composer install
3. Create your environment file.
   Windows PowerShell:
   Copy-Item .env.example .env
   macOS / Linux:
   cp .env.example .env
4. Generate the application key:
   php artisan key:generate
5. Configure your database and AI settings in .env.
6. Run database migrations:
   php artisan migrate
7. Start the development server:
   php artisan serve
8. Open the local URL shown in the terminal.
Setup commands can vary depending on the dependencies and services configured for your environment. Review the project's configuration files if a command differs from your setup.

Testing
Run the automated test suite from the project directory:
php artisan test
To run a specific test file, use its path, for example:
php artisan test tests/Feature/ExampleTest.php
Replace the example path with a test file that exists in your repository.
Future Improvements
- Improve quiz customization and difficulty controls.
- Add quiz history and progress tracking.
- Expand question-quality checks.
- Improve error handling and setup documentation.
- Add more configuration options for AI providers.
- Improve accessibility and responsive user experience.
Security
- Keep API keys, passwords, and other secrets in your local .env file.
- Never commit .env or credentials to a public repository.
- Use .env.example for non-secret configuration placehold
