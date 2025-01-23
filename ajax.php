<?php
ob_start();
date_default_timezone_set("Asia/Riyadh");
session_start();
$action = $_GET['action'];
include 'ToDoPMActions.php';
$todo_curd = new ToDoPMActions();
if ($action == 'login') {
    echo $todo_curd->login();
}
if ($action == 'confirm') {
    echo $todo_curd->confirm();
}

if ($action == 'logout') {
    echo $todo_curd->logout();
}

if ($action == 'save_user') {
    echo $todo_curd->save_user();
}
if ($action == 'delete_user') {
    echo $todo_curd->delete_user();
}
if ($action == 'save_project') {
    echo $todo_curd->save_project();
}
if ($action == 'delete_project') {
    echo $todo_curd->delete_project();
}
if ($action == 'save_task') {
    echo $todo_curd->save_task();
}
if ($action == 'delete_task') {
    echo $todo_curd->delete_task();
}
if ($action == 'save_progress') {
    echo $todo_curd->save_progress();
}
if ($action == 'delete_progress') {
    echo $todo_curd->delete_progress();
}
ob_end_flush();
?>
