@echo off
chcp 936 >nul
setlocal EnableExtensions
cd /d "%~dp0"

set "MYSQL_BASE=D:\MySQL\MySQL Server 8.0"
set "MYSQL_DATA=D:\database_project\mysql_data"
set "MYSQLD=%MYSQL_BASE%\bin\mysqld.exe"
set "PHP_EXE=D:\database_project\tools\php\php.exe"
set "WEB_ROOT=D:\database_project\public"
set "MYSQL_PORT=3306"
set "PHP_PORT=8080"

echo ==========================================
echo   旅行信息分享平台 - 启动
echo ==========================================
echo.

if not exist "%MYSQLD%" (
    echo [错误] 找不到 MySQL：%MYSQLD%
    goto :fail
)
if not exist "%PHP_EXE%" (
    echo [错误] 找不到 PHP：%PHP_EXE%
    goto :fail
)
if not exist "%MYSQL_DATA%" (
    echo [错误] 找不到 MySQL 数据目录：%MYSQL_DATA%
    goto :fail
)
if not exist "%CD%\storage\tmp" mkdir "%CD%\storage\tmp"
if not exist "%CD%\storage\sessions" mkdir "%CD%\storage\sessions"


call :port_open %MYSQL_PORT%
if not errorlevel 1 (
    echo [跳过] MySQL 已在 %MYSQL_PORT% 端口运行。
) else (
    echo [启动] MySQL ...
    start "MySQL-3306" /min "%MYSQLD%" --no-defaults --basedir="%MYSQL_BASE%" --datadir="%MYSQL_DATA%" --port=%MYSQL_PORT% --bind-address=127.0.0.1 --pid-file="%MYSQL_DATA%\mysqld.pid" --console
    call :wait_port %MYSQL_PORT% 30
    if errorlevel 1 (
        echo [错误] MySQL 30 秒内未启动，请查看 MySQL 最小化窗口中的错误信息。
        goto :fail
    )
    echo [完成] MySQL 已启动。
)

call :port_open %PHP_PORT%
if not errorlevel 1 (
    echo [跳过] PHP 已在 %PHP_PORT% 端口运行。
) else (
    echo [启动] PHP ...
    start "PHP-8080" /min "%PHP_EXE%" -S 0.0.0.0:%PHP_PORT% -t "%WEB_ROOT%"
    call :wait_port %PHP_PORT% 15
    if errorlevel 1 (
        echo [错误] PHP 15 秒内未启动。
        goto :fail
    )
    echo [完成] PHP 已启动。
)

echo.
echo 访问地址：http://127.0.0.1:%PHP_PORT%
echo 提示：MySQL 和 PHP 分别运行在最小化的窗口中；
echo       关闭对应窗口即可停止该服务。
echo.
start "" "http://127.0.0.1:%PHP_PORT%"
pause
exit /b 0

:port_open
netstat -ano | findstr ":%1 " | findstr /I "LISTEN" >nul
exit /b %errorlevel%

:wait_port
set /a __tries=0
:wait_port_loop
call :port_open %1
if not errorlevel 1 exit /b 0
set /a __tries+=1
if %__tries% GEQ %2 exit /b 1
ping 127.0.0.1 -n 2 >nul
goto :wait_port_loop

:fail
echo.
pause
exit /b 1
