<?php
echo "Server is running on port 8000";
echo "\nPHP Version: " . phpversion();
echo "\nDocument Root: " . $_SERVER['DOCUMENT_ROOT'];
echo "\nScript Name: " . $_SERVER['SCRIPT_NAME'];
echo "\nServer Name: " . $_SERVER['SERVER_NAME'];
echo "\nServer Port: " . $_SERVER['SERVER_PORT'];
