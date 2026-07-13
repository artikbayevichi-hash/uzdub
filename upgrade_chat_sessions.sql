ALTER TABLE ai_chat_messages 
ADD COLUMN session_id INT DEFAULT NULL 
AFTER id,
ADD INDEX idx_session (session_id);

SELECT 'session_id ustuni qo\'shildi' AS natija;
