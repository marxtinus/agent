<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { useEcho, useEchoPublic } from '@laravel/echo-vue';
import { ref } from 'vue';

const page = usePage();
const userId = page.props.auth.user.id;

const messages = ref<string[]>([]);
const newMessage = ref('');
const privateMessages = ref<string[]>([]);
const newPrivateMessage = ref('');

if (typeof window !== 'undefined') {
    useEchoPublic<{ message: string }>(
        'atous',
        '.message.a.tous',
        (payload) => {
            messages.value.push(payload.message);
        },
    );

    useEcho<{ message: string }>(
        `user.${userId}`,
        '.message.prive',
        (payload) => {
            privateMessages.value.push(payload.message);
        },
    );
}

function send() {
    if (!newMessage.value.trim()) {
        return;
    }

    router.post('/broadcast-test/send', { message: newMessage.value });
    newMessage.value = '';
}

function sendPrivate() {
    if (!newPrivateMessage.value.trim()) {
        return;
    }

    router.post('/broadcast-test/send-private', {
        message: newPrivateMessage.value,
    });
    newPrivateMessage.value = '';
}
</script>

<template>
    <Head title="Broadcast Test" />

    <div class="mx-auto max-w-xl p-6">
        <h1 class="mb-4 text-2xl font-bold">Test Broadcasting</h1>

        <section class="mb-8">
            <h2 class="mb-3 text-lg font-semibold">
                Canal public <code>atous</code>
            </h2>

            <form @submit.prevent="send" class="mb-4 flex gap-2">
                <input
                    v-model="newMessage"
                    type="text"
                    placeholder="Écris un message public..."
                    class="flex-1 rounded-lg border border-zinc-300 px-4 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                />
                <button
                    type="submit"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
                >
                    Envoyer
                </button>
            </form>

            <ul class="space-y-2">
                <li
                    v-for="(msg, i) in messages"
                    :key="i"
                    class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50"
                >
                    {{ msg }}
                </li>
            </ul>

            <p v-if="messages.length === 0" class="text-zinc-500">
                En attente de messages sur le canal <code>atous</code>...
            </p>
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold">
                Canal privé <code>user.{{ userId }}</code>
            </h2>

            <form @submit.prevent="sendPrivate" class="mb-4 flex gap-2">
                <input
                    v-model="newPrivateMessage"
                    type="text"
                    placeholder="Écris un message privé..."
                    class="flex-1 rounded-lg border border-zinc-300 px-4 py-2 dark:border-zinc-600 dark:bg-zinc-800"
                />
                <button
                    type="submit"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
                >
                    Envoyer
                </button>
            </form>

            <ul class="space-y-2">
                <li
                    v-for="(msg, i) in privateMessages"
                    :key="i"
                    class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-800 dark:bg-indigo-900/30"
                >
                    {{ msg }}
                </li>
            </ul>

            <p v-if="privateMessages.length === 0" class="text-zinc-500">
                En attente de messages privés sur le canal
                <code>user.{{ userId }}</code>...
            </p>
        </section>
    </div>
</template>
