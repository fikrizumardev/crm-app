<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div>
    <div class="flex items-center gap-3 lg:hidden">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">CRM</span>
        <span class="text-base font-semibold text-slate-800"><?= esc(app_setting('company_name', 'Sistem CRM')) ?></span>
    </div>
    <h1 class="mt-8 text-2xl font-semibold tracking-tight text-slate-900 lg:mt-0">Masuk ke akun Anda</h1>
    <p class="mt-2 text-sm text-slate-500">Gunakan username dan password yang diberikan admin.</p>
</div>

<div class="mt-8">
    <?= component('flash') ?>

    <form action="<?= site_url('login') ?>" method="post" class="space-y-5" x-data="{ showPassword: false, submitting: false }" @submit="submitting = true">
        <?= csrf_field() ?>

        <?= component('form-field', [
            'name'     => 'username',
            'label'    => 'Username',
            'value'    => $username,
            'required' => true,
            'attrs'    => ['autocomplete' => 'username', 'autocapitalize' => 'none', 'autofocus' => 'autofocus', 'maxlength' => '100'],
        ]) ?>

        <div>
            <label for="field-password" class="block text-sm font-medium text-slate-700">Password <span class="text-red-500">*</span></label>
            <div class="relative mt-1">
                <input id="field-password" name="password" :type="showPassword ? 'text' : 'password'" type="password" required autocomplete="current-password"
                       class="block w-full rounded-md border-0 px-3 py-2 pr-10 text-sm text-slate-900 shadow-sm ring-1 ring-inset <?= isset(form_errors()['password']) ? 'ring-red-300' : 'ring-slate-300' ?> focus:ring-2 focus:ring-inset focus:ring-brand-600">
                <button type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600" @click="showPassword = !showPassword">
                    <span class="sr-only" x-text="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"></span>
                    <?= icon('eye', 'h-4 w-4') ?>
                </button>
            </div>
            <?php if (isset(form_errors()['password'])): ?>
                <p class="mt-1 text-xs text-red-600"><?= esc(form_errors()['password']) ?></p>
            <?php endif ?>
        </div>

        <button type="submit" :disabled="submitting"
                class="flex w-full justify-center rounded-md bg-brand-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-60">
            <span x-show="!submitting">Masuk</span>
            <span x-cloak x-show="submitting">Memproses…</span>
        </button>
    </form>

    <p class="mt-8 flex items-start gap-2 rounded-md bg-slate-50 p-3 text-xs text-slate-500 ring-1 ring-inset ring-slate-200">
        <?= icon('lock-closed', 'mt-0.5 h-4 w-4 shrink-0 text-slate-400') ?>
        Lupa password? Hubungi admin aplikasi untuk reset password.
    </p>
</div>
<?= $this->endSection() ?>
