@echo off
chcp 936 >nul
setlocal EnableExtensions
cd /d "%~dp0"

set "MYSQL_BASE=D:\MySQL\MySQL Server 8.0"
set "MYSQL_DATA=D:\database_project\mysql_data"
set "MYSQLADMIN=%MYSQL_BASE%\bin\mysqladmin.exe"
set "MYSQL_PORT=3306"
set "PHP_PORT=8080"
set "STOP_FAILED="

echo ==========================================
echo   旅行信息分享平台 - 停止
echo ==========================================
echo.

echo [1/2] 停止 PHP 开发服务器 ...
set "PHP_FOUND="
for /f "tokens=5" %%P in ('netstat -ano ^| findstr ":%PHP_PORT% " ^| findstr /I "LISTEN"') do (
    set "PHP_FOUND=1"
    taskkill /PID %%P /F >nul 2>&1
)
if defined PHP_FOUND (
    call :wait_port_closed %PHP_PORT% 5
    if errorlevel 1 (
        echo [警告] PHP 进程未能停止，请以管理员身份运行本脚本。
        set "STOP_FAILED=1"
    ) else (
        echo [完成] PHP 已停止。
    )
) else (
    echo [跳过] PHP 未运行。
)

echo.
echo [2/2] 停止 MySQL ...
call :port_open %MYSQL_PORT%
if errorlevel 1 (
    echo [跳过] MySQL 未运行。
    goto :done
)

if not exist "%MYSQLADMIN%" (
    echo [错误] 找不到 mysqladmin：%MYSQLADMIN%
    goto :fail
)

if not defined MYSQL_ROOT_PASSWORD set /p "MYSQL_ROOT_PASSWORD=请输入 MySQL root 密码: "
"%MYSQLADMIN%" -h 127.0.0.1 -P %MYSQL_PORT% -u root -p%MYSQL_ROOT_PASSWORD% shutdown >nul 2>&1
call :wait_port_closed %MYSQL_PORT% 30
if errorlevel 1 (
    echo [警告] MySQL 未在 30 秒内停止，请查看 MySQL 最小化窗口中的错误信息。
    echo        可尝试手动执行：
    echo        "%MYSQL_BASE%\bin\mysqladmin.exe" -h 127.0.0.1 -P %MYSQL_PORT% -u root -p******** shutdown
    goto :fail
)
echo [完成] MySQL 已停止。

:done
echo.
if defined STOP_FAILED (
    echo 部分服务未停止，请查看上面的提示。
) else (
    echo 所有服务已停止。
)
echo.
pause
exit /b 0

:port_open
netstat -ano | findstr ":%1 " | findstr /I "LISTEN" >nul
exit /b %errorlevel%

:wait_port_closed
set /a __tries=0
:wait_closed_loop
call :port_open %1
if errorlevel 1 exit /b 0
set /a __tries+=1
if %__tries% GEQ %2 exit /b 1
ping 127.0.0.1 -n 2 >nul
goto :wait_closed_loop

:fail
echo.
pause
exit /b 1
