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
        <body class="mb-20 w-full max-w-[750px] text-sm not-has-[nav]:hidden">
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
                        Conexion
                    </Link>
                    <Link
                        :href="register()"
                        class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                    >
                        S'eregistrer
                    </Link>
                </template>
            </nav>
        </body>

        <div class="hidden h-14.5 lg:block"></div>
    </div>
</template>
