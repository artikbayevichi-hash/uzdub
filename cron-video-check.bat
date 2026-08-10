@echo off
REM UZDUB video manba tekshiruvi (kuniga bir marta ishga tushiring)
REM Windows Task Scheduler qo'shish:
REM   schtasks /create /tn "UZDUB_VideoCheck" /sc daily /st 07:00 /tr "C:\xampp\htdocs\uzdub\cron-video-check.bat"
"C:\xampp\php\php.exe" "C:\xampp\htdocs\uzdub\api\video-source-check.php"
