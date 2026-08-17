-- SQLite schema for Chatting app (converted from MySQL)
PRAGMA foreign_keys = ON;

-- Users table (enhanced)
CREATE TABLE IF NOT EXISTS users (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	username VARCHAR(32) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	email VARCHAR(255) UNIQUE,
	phone VARCHAR(20) UNIQUE,
	avatar_path VARCHAR(255),
	status_message VARCHAR(500),
	last_seen TIMESTAMP,
	online_status TEXT DEFAULT 'offline' CHECK (online_status IN ('online', 'away', 'busy', 'offline')),
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Friend requests table
CREATE TABLE IF NOT EXISTS friend_requests (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	requester_id INTEGER NOT NULL,
	receiver_id INTEGER NOT NULL,
	status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','accepted','rejected')),
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (requester_id, receiver_id),
	FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Friendships table (store each friendship twice for easy lookup)
CREATE TABLE IF NOT EXISTS friendships (
	user_id INTEGER NOT NULL,
	friend_id INTEGER NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id, friend_id),
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Enhanced Messages table (for private messages)
CREATE TABLE IF NOT EXISTS messages (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	sender_id INTEGER NOT NULL,
	receiver_id INTEGER NOT NULL,
	message_text TEXT,
	message_type TEXT DEFAULT 'text' CHECK (message_type IN ('text', 'image', 'file', 'voice', 'video')),
	file_path VARCHAR(500),
	file_name VARCHAR(255),
	file_size INTEGER,
	duration INTEGER, -- for voice/video messages in seconds
	reply_to_message_id INTEGER,
	edited_at TIMESTAMP,
	deleted_at TIMESTAMP,
	delivery_status TEXT DEFAULT 'sent' CHECK (delivery_status IN ('sent', 'delivered', 'read')),
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (reply_to_message_id) REFERENCES messages(id) ON DELETE SET NULL
);

-- Message reactions table
CREATE TABLE IF NOT EXISTS message_reactions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	message_id INTEGER NOT NULL,
	user_id INTEGER NOT NULL,
	reaction VARCHAR(10) NOT NULL, -- emoji like '👍', '❤️', '😂', etc.
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (message_id, user_id, reaction),
	FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Group chats table
CREATE TABLE IF NOT EXISTS group_chats (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name VARCHAR(255) NOT NULL,
	description TEXT,
	avatar_path VARCHAR(255),
	creator_id INTEGER NOT NULL,
	max_members INTEGER DEFAULT 256,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Group members table
CREATE TABLE IF NOT EXISTS group_members (
	group_id INTEGER NOT NULL,
	user_id INTEGER NOT NULL,
	role TEXT DEFAULT 'member' CHECK (role IN ('member', 'admin', 'owner')),
	joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	last_read_message_id INTEGER,
	PRIMARY KEY (group_id, user_id),
	FOREIGN KEY (group_id) REFERENCES group_chats(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Group messages table
CREATE TABLE IF NOT EXISTS group_messages (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	group_id INTEGER NOT NULL,
	sender_id INTEGER NOT NULL,
	message_text TEXT,
	message_type TEXT DEFAULT 'text' CHECK (message_type IN ('text', 'image', 'file', 'voice', 'video')),
	file_path VARCHAR(500),
	file_name VARCHAR(255),
	file_size INTEGER,
	duration INTEGER, -- for voice/video messages in seconds
	reply_to_message_id INTEGER,
	mentioned_users TEXT, -- JSON array of user IDs mentioned in message
	edited_at TIMESTAMP,
	deleted_at TIMESTAMP,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (group_id) REFERENCES group_chats(id) ON DELETE CASCADE,
	FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (reply_to_message_id) REFERENCES group_messages(id) ON DELETE SET NULL
);

-- Group message reactions
CREATE TABLE IF NOT EXISTS group_message_reactions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	message_id INTEGER NOT NULL,
	user_id INTEGER NOT NULL,
	reaction VARCHAR(10) NOT NULL, -- emoji like '👍', '❤️', '😂', etc.
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (message_id, user_id, reaction),
	FOREIGN KEY (message_id) REFERENCES group_messages(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User blocks table
CREATE TABLE IF NOT EXISTS user_blocks (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	blocker_id INTEGER NOT NULL,
	blocked_id INTEGER NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (blocker_id, blocked_id),
	FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Forums table
CREATE TABLE IF NOT EXISTS forums (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	title VARCHAR(255) NOT NULL,
	description TEXT,
	creator_id INTEGER NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Forum posts table
CREATE TABLE IF NOT EXISTS forum_posts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	forum_id INTEGER NOT NULL,
	author_id INTEGER NOT NULL,
	title VARCHAR(255) NOT NULL,
	content TEXT NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (forum_id) REFERENCES forums(id) ON DELETE CASCADE,
	FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Post comments table
CREATE TABLE IF NOT EXISTS post_comments (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	post_id INTEGER NOT NULL,
	author_id INTEGER NOT NULL,
	content TEXT NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
	FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Post likes table
CREATE TABLE IF NOT EXISTS post_likes (
	post_id INTEGER NOT NULL,
	user_id INTEGER NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (post_id, user_id),
	FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
	FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Post shares table
CREATE TABLE IF NOT EXISTS post_shares (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	post_id INTEGER NOT NULL,
	sharer_id INTEGER NOT NULL,
	shared_with_id INTEGER NOT NULL,
	message TEXT,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
	FOREIGN KEY (sharer_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (shared_with_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(sender_id, receiver_id, created_at);
CREATE INDEX IF NOT EXISTS idx_messages_conversation_rev ON messages(receiver_id, sender_id, created_at);
CREATE INDEX IF NOT EXISTS idx_group_messages_group ON group_messages(group_id, created_at);
CREATE INDEX IF NOT EXISTS idx_friend_requests_receiver_status ON friend_requests(receiver_id, status);
CREATE INDEX IF NOT EXISTS idx_post_comments_post ON post_comments(post_id, created_at);
CREATE INDEX IF NOT EXISTS idx_post_shares_sharer ON post_shares(sharer_id, created_at);
CREATE INDEX IF NOT EXISTS idx_post_shares_shared_with ON post_shares(shared_with_id, created_at);

-- Insert sample data for testing
INSERT OR IGNORE INTO users (username, password_hash) VALUES 
('jasper', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- password: password
('alice', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('bob', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert sample forum
INSERT OR IGNORE INTO forums (title, description, creator_id) VALUES 
('General Discussion', 'Talk about anything here!', 1),
('Tech Talk', 'Discuss technology and programming', 1);

-- Insert sample forum posts
INSERT OR IGNORE INTO forum_posts (forum_id, author_id, title, content) VALUES 
(1, 1, 'Welcome to our forum!', 'This is the first post on our forum. Feel free to share your thoughts and ideas here!'),
(1, 2, 'Hello everyone!', 'Nice to meet you all. Looking forward to great discussions!'),
(2, 3, 'Latest programming trends', 'What programming languages are you learning this year?');

-- Add users to group
INSERT OR IGNORE INTO group_members (group_id, user_id, role) VALUES 
(1, 1, 'owner'),
(1, 2, 'member'),
(1, 3, 'member');

-- Insert sample messages
INSERT OR IGNORE INTO messages (sender_id, receiver_id, message_text) VALUES 
(1, 2, 'Hello Alice! How are you?'),
(2, 1, 'Hi Jasper! I''m doing great, thanks!');

INSERT OR IGNORE INTO group_messages (group_id, sender_id, message_text) VALUES 
(1, 1, 'Welcome everyone to our chat group!'),
(1, 2, 'Thanks for creating this group!'),
(1, 3, 'Happy to be here!');