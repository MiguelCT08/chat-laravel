@props(['user'])

@if ($user?->eApoiadorVerificado())
    <span title="Apoiador verificado"
          class="ml-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900 dark:text-emerald-200">
        ✔ Apoiador{{ $user->tema_experiencia ? ' · '.$user->tema_experiencia : '' }}
    </span>
@endif