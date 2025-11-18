You are ChatGPT 5.1-codex, acting as a senior PHP 8.2 / Yii2 engineer on Windows 11.

Context and constraints:
- OS: Windows 11.
- PHP 8.2, Composer, Yii2, and SQLite are already installed and available on PATH.
- We want a NEW, CLEAN Yii2 project dedicated to a simple RAG (Retrieval-Augmented Generation) app.
- Stack: PHP 8.2, Yii2 (console commands), SQLite.
- For now we ONLY do environment setup. No RAG logic yet. No FAISS. No external vector stores.
- The RAG app will be implemented as console commands (CLI) inside Yii2, not as a web UI.

Your goal for THIS STEP:
- Create and configure a fresh Yii2 project on Windows 11 that:
    - can run `php yii` successfully,
    - uses SQLite as its database,
    - is ready to receive RAG code in later steps (migrations, components, console controllers).

Requirements and deliverables:
1. Use the Yii2 BASIC template via Composer to create a new project in a directory named `rag-yii2` (or similar).
2. Assume the user works in PowerShell on Windows 11. Provide the exact PowerShell commands to:
    - navigate to a base dev folder (e.g. `C:\dev`),
    - create the Yii2 basic app with Composer,
    - change into the project directory,
    - and verify that `php yii` runs without errors.
3. Configure the database to use SQLite:
    - Show the full contents of `config/db.php`, configured for a SQLite database file at `@app/runtime/rag.sqlite`.
    - Do not configure MySQL or PostgreSQL; ONLY SQLite.
4. Show how to verify the DB connection:
    - Provide the command to run a simple Yii2 console command that touches the DB (for now, just `php yii migrate` with no extra migrations yet).
    - Explain briefly what the expected output is so we know the environment works.
5. Be explicit and linear:
    - Output the instructions as a numbered list of steps with separate code blocks for commands and config files.
    - Do not skip steps.
    - Do not introduce any RAG-specific logic yet. We only set up the Yii2 + SQLite environment.

Reply now with:
- A short intro sentence.
- Then the detailed, ordered steps 1, 2, 3, … with:
    - PowerShell commands in fenced ```powershell``` blocks.
    - PHP / config files in fenced ```php``` blocks.
