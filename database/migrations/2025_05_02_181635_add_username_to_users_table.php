<?php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('username')->unique()->after('email');
    });
}
