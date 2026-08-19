export interface Conversation {
    id: string;
    title: string;
    updated_at: string;
}

export interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
}

export interface LocalChatMessage extends ChatMessage {
    status: 'streaming' | 'complete' | 'error';
}
