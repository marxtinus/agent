<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { chat, login, logout } from '@/routes';
import { register } from '@/routes';
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div
        class="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]"
    >
        <img
            src="/images/MarxtLogo.jpg"
            alt="Marxt logo"
            class="mb-20 rounded-full object-cover shadow-lg lg:h-60 lg:w-60"
        />

        <div v-if="!$page.props.auth.user" class="mb-8 text-center">
            <h1
                class="text-2xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]"
            >
                Bienvenue sur Marxtinus !
            </h1>
            <p
                class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-[#19140099] dark:text-[#EDEDEC99]"
            >
                Pour commencer, enregistrez-vous avec votre courriel ou
                connectez-vous si vous avez déjà un compte. Vous pouvez aussi
                vous connecter rapidement avec
                <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]"
                    >Google</span
                >,
                <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]"
                    >GitHub</span
                >
                ou
                <span class="font-medium text-[#1b1b18] dark:text-[#EDEDEC]"
                    >Yahoo</span
                >.
            </p>
        </div>
        <div v-else class="mb-8 text-center">
            <h1
                class="text-2xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]"
            >
                Bon retour, bienvenue !
            </h1>
            <p
                class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-[#19140099] dark:text-[#EDEDEC99]"
            >
                Lancez le chatbot avec le bouton Chat pour commencer une
                discussion.
            </p>
        </div>
        <div class="mb-20 w-full max-w-187.5 text-sm not-has-[nav]:hidden">
            <nav class="flex items-center justify-center gap-4">
                <template v-if="$page.props.auth.user">
                    <Link
                        :href="chat()"
                        class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                    >
                        Chat
                    </Link>
                    <Link
                        :href="logout()"
                        class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                    >
                        Déconnexion
                    </Link>
                </template>
                <template v-else>
                    <Link
                        :href="login()"
                        class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                    >
                        Connexion
                    </Link>
                    <Link
                        :href="register()"
                        class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                    >
                        S'enregistrer
                    </Link>
                </template>
            </nav>
        </div>

        <div class="hidden h-14.5 lg:block"></div>
    </div>
</template>
