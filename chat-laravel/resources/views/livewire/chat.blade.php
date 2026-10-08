<?php

use App\Models\Conversation;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $conversationId = null;
    public string $body = '';

    #[Computed]
    public function conversations()
    {
        return Conversation::query()
            ->whereHas('users', fn ($q) => $q->whereKey(auth()->id()))
            ->with(['users', 'latestMessage'])
            ->get()
            ->sortByDesc(fn ($c) => $c->latestMessage?->created_at ?? $c->created_at)
            ->values();
    }

    #[Computed]
    public function people()
    {
        return User::where('id', '!=', auth()->id())->orderBy('name')->get();
    }

    #[Computed]
    public function current(): ?Conversation
    {
        if (! $this->conversationId) {
            return null;
        }

        return Conversation::with('users')
            ->whereHas('users', fn ($q) => $q->whereKey(auth()->id()))
            ->find($this->conversationId);
    }

    #[Computed]
    public function chatMessages()
    {
        if (! $this->current) {
            return collect();
        }

        return $this->current->messages()->with('user')->latest()->limit(100)->get();
    }

    public function select(int $id): void
    {
        $this->conversationId = $id;
        $this->markAsRead();
    }

    public function start(int $userId): void
    {
        abort_if($userId === auth()->id(), 403);
        User::findOrFail($userId);

        $conversation = Conversation::where('is_group', false)
            ->whereHas('users', fn ($q) => $q->whereKey(auth()->id()))
            ->whereHas('users', fn ($q) => $q->whereKey($userId))
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->users()->attach([auth()->id(), $userId]);
        }

        $this->conversationId = $conversation->id;
    }

    public function send(): void
    {
        $this->validate(['body' => 'required|string|max:2000']);

        abort_unless($this->current, 403);

        $this->current->messages()->create([
            'user_id' => auth()->id(),
            'body' => trim($this->body),
        ]);

        $this->reset('body');
    }

    public function markAsRead(): void
    {
        $this->current?->messages()
            ->where('user_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}; ?>

<div wire:poll.3s="markAsRead"
     class="flex h-[70vh] overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">

    {{-- Coluna da esquerda --}}
    <aside class="w-1/3 overflow-y-auto border-r border-gray-200 dark:border-gray-700">
        <h3 class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">Conversas</h3>

        @forelse ($this->conversations as $conversation)
            <button wire:key="conv-{{ $conversation->id }}"
                    wire:click="select({{ $conversation->id }})"
                    class="block w-full px-4 py-3 text-left hover:bg-gray-100 dark:hover:bg-gray-700 {{ $conversationId === $conversation->id ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                <div class="font-medium text-gray-900 dark:text-gray-100">
                    {{ $conversation->name ?: $conversation->users->firstWhere('id', '!=', auth()->id())?->name }}
                </div>
                <div class="truncate text-sm text-gray-500">
                    {{ $conversation->latestMessage?->body ?? 'Sem mensagens' }}
                </div>
            </button>
        @empty
            <p class="px-4 py-2 text-sm text-gray-500">Nenhuma conversa ainda.</p>
        @endforelse

        <h3 class="mt-4 px-4 py-3 text-xs font-semibold uppercase text-gray-500">Iniciar conversa com</h3>
        @foreach ($this->people as $person)
            <button wire:key="person-{{ $person->id }}"
                    wire:click="start({{ $person->id }})"
                    class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                {{ $person->name }}
            </button>
        @endforeach
    </aside>

    {{-- Coluna da direita --}}
    <section class="flex w-2/3 flex-col">
        @if ($this->current)
            <header class="border-b border-gray-200 px-4 py-3 font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                {{ $this->current->name ?: $this->current->users->firstWhere('id', '!=', auth()->id())?->name }}
            </header>

            {{-- flex-col-reverse mantém a rolagem ancorada nas mensagens mais novas --}}
            <div class="flex flex-1 flex-col-reverse gap-2 overflow-y-auto p-4">
                @foreach ($this->chatMessages as $message)
                    @php($mine = $message->user_id === auth()->id())
                    <div wire:key="msg-{{ $message->id }}" class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[75%] rounded-lg px-3 py-2 {{ $mine ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-900 dark:bg-gray-700 dark:text-gray-100' }}">
                            <p class="whitespace-pre-line break-words">{{ $message->body }}</p>
                            <p class="mt-1 text-right text-xs opacity-70">{{ $message->created_at->format('H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <form wire:submit="send" class="flex gap-2 border-t border-gray-200 p-3 dark:border-gray-700">
                <input type="text" wire:model="body" placeholder="Digite uma mensagem..." autocomplete="off"
                       class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                <button type="submit"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Enviar
                </button>
            </form>
        @else
            <div class="flex flex-1 items-center justify-center text-gray-500">
                Selecione uma conversa ou inicie uma nova.
            </div>
        @endif
    </section>
</div>
