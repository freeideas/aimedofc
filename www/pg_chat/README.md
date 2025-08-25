# pg_chat - AI Medical Assistant Chat Interface

## Overview

The chat interface provides patients with AI-powered medical consultation assistance using Google's Gemini 2.0 Flash Pro model. The interface features a two-panel layout with conversation history on the left and the active chat on the right.

## Features

### Conversation Management
- **Conversation List (Left Panel)**: Shows all past conversations with titles and timestamps
- **New Chat Button**: Creates a new conversation thread
- **Auto-titling**: First message becomes the conversation title (truncated to 50 chars)
- **Persistence**: All conversations are saved and can be resumed

### Chat Interface (Right Panel)
- **Initial Greeting**: Every conversation starts with:
  ```
  Hello [Patient Name]! I am not a doctor, but I have read all your records and I am ready to answer any questions you have.
  ```
- **Real-time Messaging**: Patient messages appear on the right, AI responses on the left
- **Medical Context**: AI has access to all patient medical records during conversation
- **Conversation History**: AI maintains context within each conversation thread

## Technical Implementation

### Database Schema
Uses the updated schema with descriptive column names:
- `conversations` table with `conversation_id` as primary key
- `chat_messages` table with `message_id` as primary key
- Proper foreign key relationships

### AI Integration
- **Model**: Gemini 2.0 Flash Pro (accessed via Google API)
- **API Key Location**: `../../.creds.json` under `GOOGLE.API_KEY`
- **Context**: Includes all patient medical records plus conversation history
- **System Prompt**: Emphasizes the AI is not a doctor but a helpful assistant

### Files
- `index.php` - Main chat interface with sidebar and chat area
- `api_chat.php` - Handles message submission and AI response generation
- `api_get_conversation.php` - Retrieves conversation history
- `style.css` - Responsive styling with gradient header

### Authentication
- Requires valid session via `checkAuth()`
- User ID retrieved from session for all database operations

## User Flow

1. User clicks "Chat with AI" from main dashboard
2. If no conversations exist, shows "(new chat)" placeholder
3. User types first message
4. System creates new conversation, saves message, generates AI response
5. Conversation title updates to first message (truncated)
6. User can continue chatting or start new conversation
7. All conversations persist and can be resumed later

## Security

- Session-based authentication required
- User can only access their own conversations
- SQL injection prevention via prepared statements
- XSS prevention via `htmlspecialchars()` for output

## Dependencies

- PHP 8.1+ with PDO SQLite
- Google Gemini API access
- Valid session from pg_login
- Patient medical records in database