<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimestampsToBirthContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('birth_contacts')) {
            Schema::table('birth_contacts', function (Blueprint $table) {
                //
                if (!(Schema::hasColumn('birth_contacts', 'created_at') AND Schema::hasColumn('birth_contacts', 'updated_at'))) {
                    $table->timestamps();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('birth_contacts', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
}
