@echo off
echo 启动KinCount后端服务...
cd /d "d:\git\kincount\kincount-backend"
php think run -H 127.0.0.1 -p 82
pause