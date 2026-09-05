@echo off
REM ============================================================
REM  Blood Donor Management System - scheduled backup (Windows)
REM
REM  Task Scheduler cannot run a .sh directly, so this wrapper sets
REM  the environment scripts\backup.sh expects and hands off to Git
REM  Bash. On a Linux production host, skip this file entirely and
REM  use cron - the line is in the header of backup.sh.
REM
REM  Run it by hand to test:
REM      scripts\backup-windows.cmd
REM
REM  Every run appends to storage\logs\backup.log, so a failure that
REM  happens at 02:15 leaves evidence rather than silence.
REM ============================================================

setlocal

set "PROJECT=%~dp0.."
pushd "%PROJECT%"

REM ---- Paths to the XAMPP MySQL client tools -----------------
set "MYSQL_BIN=C:\xampp\mysql\bin\mysql.exe"
set "DUMP_BIN=C:\xampp\mysql\bin\mysqldump.exe"

REM ---- Database ----------------------------------------------
REM DB_USER defaults to root only because no least-privilege user
REM exists yet. Once docs\create-db-user.sql has been run, set:
REM     DB_USER=bdms_backup
REM in scripts\backup.env (git-ignored) and put its password in a
REM MySQL option file - never on the command line, where every user
REM on the machine can read it from the process list.
set "DB_NAME=blood_donor_system"
set "DB_USER=root"
set "RETAIN_DAYS=14"

REM ---- Optional local overrides ------------------------------
REM scripts\backup.env may set DB_USER, BACKUP_DIR, RETAIN_DAYS.
REM eol=# skips comment lines; delims== splits KEY=VALUE.
if exist "%PROJECT%\scripts\backup.env" (
    for /f "usebackq eol=# tokens=1,* delims==" %%A in ("%PROJECT%\scripts\backup.env") do set "%%A=%%B"
)

REM ---- Locate Git Bash ---------------------------------------
set "BASH=C:\Program Files\Git\bin\bash.exe"
if not exist "%BASH%" set "BASH=C:\Program Files (x86)\Git\bin\bash.exe"
if not exist "%BASH%" (
    echo [%date% %time%] ERROR: Git Bash not found. Install Git for Windows or edit this file.
    popd
    exit /b 1
)

if not exist "%PROJECT%\storage\logs" mkdir "%PROJECT%\storage\logs"

echo. >> "%PROJECT%\storage\logs\backup.log"
echo ===== run started %date% %time% ===== >> "%PROJECT%\storage\logs\backup.log"

"%BASH%" -c "export MYSQL_BIN='%MYSQL_BIN%' DUMP_BIN='%DUMP_BIN%' DB_NAME='%DB_NAME%' DB_USER='%DB_USER%' RETAIN_DAYS='%RETAIN_DAYS%'; cd \"$(cygpath -u '%PROJECT%')\" && ./scripts/backup.sh" >> "%PROJECT%\storage\logs\backup.log" 2>&1

set "RESULT=%ERRORLEVEL%"

if "%RESULT%"=="0" (
    echo ===== run finished OK %date% %time% ===== >> "%PROJECT%\storage\logs\backup.log"
) else (
    echo ===== run FAILED exit %RESULT% %date% %time% ===== >> "%PROJECT%\storage\logs\backup.log"
)

popd
endlocal & exit /b %RESULT%
