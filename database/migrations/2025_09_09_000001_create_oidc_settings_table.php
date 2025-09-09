<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOidcSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('oidc_settings', function (Blueprint $table) {
            $table->id();
            $table->string('issuer_url')->nullable();
            $table->string('authorize_url')->nullable();
            $table->string('userinfo_url')->nullable();
            $table->string('jwks_url')->nullable();
            $table->string('logout_url')->nullable();
            $table->json('allowed_mobile_redirect_uris')->nullable();
            $table->string('button_text')->default('Login with OIDC');
            $table->string('button_icon')->default('fa-solid fa-key');
            $table->json('scopes')->nullable();
            $table->boolean('auto_launch')->default(false);
            $table->boolean('auto_register')->default(true);
            $table->string('group_claim')->nullable();
            $table->string('permission_claim')->nullable();
            $table->boolean('ldap_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('oidc_settings');
    }
}
