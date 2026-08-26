<?php
use App\Core\Flash;

$mensaje = Flash::get('success');
$error   = Flash::get('error');
?>

<?php if (!empty($mensaje)): ?>
    <div class="bg-green-50 text-green-700 border border-green-200 rounded-xl p-4 text-sm mb-6 flex items-start gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[20px] shrink-0 text-green-600">check_circle</span>
        <span><?= e($mensaje) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl p-4 text-sm mb-6 flex items-start gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[20px] shrink-0 text-red-600">error</span>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>