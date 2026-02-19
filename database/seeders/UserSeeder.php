<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@localstock.com'],
            [
                'name' => 'Administrador',
                'email' => 'admin@localstock.com',
                'password' => Hash::make('123456'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ Admin criado: admin@localstock.com / senha: 123456');

        // Criar Admin de teste (para testes)
        $adminTest = User::firstOrCreate(
            ['email' => 'test@localstock.com'],
            [
                'name' => 'Admin Teste',
                'email' => 'test@localstock.com',
                'password' => Hash::make('123456'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✓ Admin Teste criado: test@localstock.com / senha: 123456');

        // Criar Customer Normal (Utilizador Simples)
        $customerNormal = Customer::firstOrCreate(
            ['email' => 'customer@localstock.com'],
            [
                'first_name' => 'João',
                'last_name' => 'Silva',
                'user_name' => 'joaosilva',
                'slug' => 'joao-silva',
                'email' => 'customer@localstock.com',
                'password' => Hash::make('123456'),
                'contact_number' => '+351912345678',
                'email_verified_at' => now(),
                'status' => ACTIVE,
                'role' => CUSTOMER_ROLE_CUSTOMER,
                'contributor_apply' => CONTRIBUTOR_APPLY_NO,
                'contributor_status' => CONTRIBUTOR_STATUS_PENDING,
            ]
        );

        $this->command->info('✓ Customer Normal criado: customer@localstock.com / senha: 123456');

        // Criar Customer Contribuidor Pendente (Aguardando Aprovação)
        $contributorPending = Customer::firstOrCreate(
            ['email' => 'contributor@pending.localstock.com'],
            [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'user_name' => 'mariasantos',
                'slug' => 'maria-santos',
                'email' => 'contributor@pending.localstock.com',
                'password' => Hash::make('123456'),
                'contact_number' => '+351912345679',
                'email_verified_at' => now(),
                'status' => ACTIVE,
                'role' => CUSTOMER_ROLE_CONTRIBUTOR,
                'contributor_apply' => CONTRIBUTOR_APPLY_YES,
                'contributor_status' => CONTRIBUTOR_STATUS_PENDING,
            ]
        );

        $this->command->info('✓ Contribuidor Pendente criado: contributor@pending.localstock.com / senha: 123456');

        // Criar Customer Contribuidor Aprovado (Pode fazer upload)
        $contributorApproved = Customer::firstOrCreate(
            ['email' => 'contributor@localstock.com'],
            [
                'first_name' => 'Pedro',
                'last_name' => 'Costa',
                'user_name' => 'pedrocosta',
                'slug' => 'pedro-costa',
                'email' => 'contributor@localstock.com',
                'password' => Hash::make('123456'),
                'contact_number' => '+351912345680',
                'email_verified_at' => now(),
                'status' => ACTIVE,
                'role' => CUSTOMER_ROLE_CONTRIBUTOR,
                'contributor_apply' => CONTRIBUTOR_APPLY_YES,
                'contributor_status' => CONTRIBUTOR_STATUS_APPROVED,
            ]
        );

        $this->command->info('✓ Contribuidor Aprovado criado: contributor@localstock.com / senha: 123456');

        // Criar Customer Desabilitado (para testes)
        $customerDisabled = Customer::firstOrCreate(
            ['email' => 'disabled@localstock.com'],
            [
                'first_name' => 'Ana',
                'last_name' => 'Ferreira',
                'user_name' => 'anaferreira',
                'slug' => 'ana-ferreira',
                'email' => 'disabled@localstock.com',
                'password' => Hash::make('123456'),
                'contact_number' => '+351912345681',
                'email_verified_at' => now(),
                'status' => DISABLE,
                'role' => CUSTOMER_ROLE_CUSTOMER,
                'contributor_apply' => CONTRIBUTOR_APPLY_NO,
                'contributor_status' => CONTRIBUTOR_STATUS_PENDING,
            ]
        );

        $this->command->info('✓ Customer Desabilitado criado: disabled@localstock.com / senha: 123456');

        // Criar Customer Contribuidor em Hold (Suspenso)
        $contributorHold = Customer::firstOrCreate(
            ['email' => 'contributor@hold.localstock.com'],
            [
                'first_name' => 'Carlos',
                'last_name' => 'Oliveira',
                'user_name' => 'carlosoliveira',
                'slug' => 'carlos-oliveira',
                'email' => 'contributor@hold.localstock.com',
                'password' => Hash::make('123456'),
                'contact_number' => '+351912345682',
                'email_verified_at' => now(),
                'status' => ACTIVE,
                'role' => CUSTOMER_ROLE_CONTRIBUTOR,
                'contributor_apply' => CONTRIBUTOR_APPLY_YES,
                'contributor_status' => CONTRIBUTOR_STATUS_HOLD,
            ]
        );

        $this->command->info('✓ Contribuidor em Hold criado: contributor@hold.localstock.com / senha: 123456');

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  RESUMO DOS USUÁRIOS CRIADOS');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('ADMIN:');
        $this->command->info('  Email: admin@localstock.com');
        $this->command->info('  Senha: 123456');
        $this->command->info('');
        $this->command->info('ADMIN TESTE:');
        $this->command->info('  Email: test@localstock.com');
        $this->command->info('  Senha: 123456');
        $this->command->info('');
        $this->command->info('CUSTOMER NORMAL (Utilizador Simples):');
        $this->command->info('  Email: customer@localstock.com');
        $this->command->info('  Senha: 123456');
        $this->command->info('  Status: Ativo');
        $this->command->info('');
        $this->command->info('CONTRIBUIDOR PENDENTE (Aguardando Aprovação):');
        $this->command->info('  Email: contributor@pending.localstock.com');
        $this->command->info('  Senha: 123456');
        $this->command->info('  Status: Ativo, mas não pode fazer upload');
        $this->command->info('');
        $this->command->info('CONTRIBUIDOR APROVADO (Pode fazer upload):');
        $this->command->info('  Email: contributor@localstock.com');
        $this->command->info('  Senha: 123456');
        $this->command->info('  Status: Ativo e aprovado');
        $this->command->info('');
        $this->command->info('CUSTOMER DESABILITADO:');
        $this->command->info('  Email: disabled@localstock.com');
        $this->command->info('  Senha: 123456');
        $this->command->info('  Status: Desabilitado');
        $this->command->info('');
        $this->command->info('CONTRIBUIDOR EM HOLD (Suspenso):');
        $this->command->info('  Email: contributor@hold.localstock.com');
        $this->command->info('  Senha: 123456');
        $this->command->info('  Status: Em suspensão');
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
    }
}


