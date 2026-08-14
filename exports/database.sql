-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: uzdub
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','2026-07-06 04:45:53'),(4,'doniyorbek0998','$2y$10$wgrUJG8raPpFoYXolfyWJu/fHvJ0O2ffwAzT2hIy2Hn5XI/8WGo2O','2026-07-17 03:52:24');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_chat_messages`
--

DROP TABLE IF EXISTS `ai_chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `role` enum('user','assistant') NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `fk_ai_chat_conversation` (`conversation_id`),
  KEY `idx_session` (`session_id`),
  CONSTRAINT `ai_chat_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_chat_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=235 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_chat_messages`
--

LOCK TABLES `ai_chat_messages` WRITE;
/*!40000 ALTER TABLE `ai_chat_messages` DISABLE KEYS */;
INSERT INTO `ai_chat_messages` VALUES (1,NULL,4,1,'user','salom','2026-07-06 04:54:55'),(2,NULL,4,1,'assistant','Salom!','2026-07-06 04:55:18'),(3,NULL,4,2,'user','salom','2026-07-06 04:55:25'),(4,NULL,4,2,'assistant','Salom, sizning yordamchim. Men faqat kino, anime va multfilmlar haqida yordam bera olaman 🎬','2026-07-06 04:55:34'),(5,NULL,4,3,'user','salom','2026-07-06 04:56:01'),(6,NULL,4,3,'assistant','Salom, sizning yordamchim!','2026-07-06 04:56:05'),(7,NULL,4,3,'user','menga kino tavsuya qil','2026-07-06 04:56:21'),(8,NULL,4,3,'assistant','Men sizga quyidagi filmlarni tavsiya qilaman: \n1. \'Klassik\' - \'Pulp Fiction\', Quentin Tarantino tomonidan suratga olingan, bu filmning syujeti va personajlari haqida ko\'proq bilish uchun batafsil ma\'lumotni ochib qo\'yaman.\n2. \'Action\' - \'Mad Max: Fury Road\', bu filmning reytingi yuqori bo\'lganligi sababli, siz uni tomosha qila olasiz.','2026-07-06 04:56:46'),(9,NULL,4,3,'user','menga ekshin kino tavsiya qil','2026-07-06 04:57:16'),(10,NULL,4,3,'assistant','Men faqat kino, anime va multfilmlar haqida yordam bera olaman ','2026-07-06 04:57:28'),(11,NULL,4,3,'user','ekshin kino tavsiya qil','2026-07-06 04:57:39'),(12,NULL,4,3,'assistant','Men sizga quyidagi filmlarni tavsiya qilaman: \'Ekshin\' janri uchun mashhur filmlar, masalan, \'John Wick\', \'Atomic Blonde\', \'Mission: Impossible - Fallout\'.','2026-07-06 04:57:52'),(13,NULL,4,1,'user','hosh menga suhbatdosh bola olasanmi','2026-07-06 05:03:33'),(14,NULL,4,1,'assistant','Men faqat kino, anime va multfilmlar haqida yordam bera olaman','2026-07-06 05:04:07'),(15,NULL,4,4,'user','salom','2026-07-06 05:19:25'),(16,NULL,4,4,'assistant','Salom, sizga yordam bera olaman','2026-07-06 05:19:56'),(17,NULL,4,5,'user','salom','2026-07-06 05:48:05'),(18,NULL,4,5,'assistant','Salom!','2026-07-06 05:48:36'),(19,NULL,4,5,'user','salom','2026-07-06 05:48:47'),(20,NULL,4,5,'assistant','Men faqat kino, anime va multfilmlar haqida yordam bera olaman','2026-07-06 05:48:55'),(21,NULL,4,5,'user','ekshin janrdagi kino tavsiya qil','2026-07-06 05:49:10'),(22,NULL,4,5,'assistant','Ekshin janrida ko\'plab mashhur filmlar mavjud, lekin ba\'zilarimdan bittadan boshlashni tavsiya qilaman. Misol uchun, \'The Matrix\' (1999), \'Inception\' (2010) va \'John Wick\' (2014) filmlari kuchli ekshin janrida bo\'lgan filmlar hisoblanadi.','2026-07-06 05:49:30'),(23,NULL,2,6,'user','salom','2026-07-06 06:02:34'),(24,NULL,2,6,'assistant','Salom! Men faqat kino, anime va multfilmlar haqida yordam bera olaman 🎬','2026-07-06 06:03:07'),(25,NULL,2,6,'user','salom','2026-07-06 06:30:35'),(26,NULL,2,6,'assistant','Salom! Siz kimdan murojaat qilaysiz?','2026-07-06 06:31:13'),(27,NULL,2,6,'user','salom','2026-07-06 07:25:46'),(28,NULL,2,6,'assistant','Salom!','2026-07-06 07:26:33'),(29,NULL,2,6,'user','yaxshimisan','2026-07-06 07:29:55'),(30,NULL,2,6,'assistant','Yaxshimisan!','2026-07-06 07:30:03'),(31,NULL,4,5,'user','salom','2026-07-07 06:04:15'),(32,NULL,4,5,'assistant','Salom!','2026-07-07 06:04:58'),(33,NULL,4,5,'user','salom','2026-07-07 06:05:16'),(34,NULL,4,5,'assistant','Salom!','2026-07-07 06:05:22'),(35,NULL,4,5,'user','salom','2026-07-07 06:29:29'),(36,NULL,4,5,'assistant','Salom!','2026-07-07 06:30:17'),(37,NULL,4,5,'user','salom','2026-07-07 06:30:33'),(38,NULL,4,5,'assistant','Salom!','2026-07-07 06:30:47'),(39,NULL,4,NULL,'user','salom','2026-07-08 14:07:16'),(40,NULL,4,NULL,'assistant','Salom! Yana bir bor salomlashamiz 😊 Sizga kino, anime yoki multfilm bo‘yicha qandaydir tavsiya kerakmi? Muayyan janr yoki kayfiyat bo‘yicha aytishingiz mumkin, bemalol yordam beraman!','2026-07-08 14:07:16'),(41,NULL,4,NULL,'user','salom','2026-07-08 14:11:02'),(42,NULL,4,NULL,'assistant','Salom! 😊\nSizga qanday yordam bera olaman? Kino, anime yoki multfilm tavsiyasi kerakmi?','2026-07-08 14:11:02'),(43,NULL,4,NULL,'user','hoy yaqshimisan','2026-07-08 14:11:45'),(44,NULL,4,NULL,'assistant','Rahmat, yaxshiman! 😊 Men sun\'iy intellektman, shuning uchun his-tuyg\'ularim yo\'q, lekin sizga yordam berishga tayyorman.\n\nSiz qalaysiz? Bugun kino, anime yoki multfilm bo\'ylab nima ko\'rmoqchisiz? Tavsiya beraymi?','2026-07-08 14:11:45'),(45,NULL,4,NULL,'user','anime tavsiya qil','2026-07-08 14:12:53'),(46,NULL,4,NULL,'assistant','Albatta! Sizga **qaysi janr** yoqadi yoki **oxirgi ko‘rgan yoqtirgan animelariz** qaysilar ekanligini bilmasam ham, turli xil mashhur va yuqori reytingli variantlardan birnechtasini taklif qilaman. Birini tanlang yoki qiziqgan janrni aytib bering:\n\n---\n\n### 🎯 **Agar yangi boshlovchi bo‘lsangiz / Umumiy hitlar:**\n| Anime | Janr | Qisqacha |\n|-------|------|----------|\n| **Attack on Titan (Shingeki no Kyojin)** | Action, Mystery, Dark Fantasy | Insonlarni yutib yurib boradigan devlarga qarshi kurash. Juda kuchli plot twistlar bor. |\n| **Fullmetal Alchemist: Brotherhood** | Adventure, Fantasy, Steampunk | Ikki aka alchimiya yordamida o‘ta tinchlikni tiklashga harakat qiladi. **Ko‘pgina “eng yaxshi anime” deb ataladi.** |\n| **Death Note** | Psychological, Thriller, Supernatural | O‘lirish defteri topgan talaba adolatni o‘z qo‘lida tutmoqchi bo‘ladi. Aqlli o‘yinlar yoqtirsa — shu. |\n| **Jujutsu Kaisen** | Supernatural, Action, Modern Fantasy | Jinnlarga qarshi kurash, zamonaviy animatsiya va ajoyib janglar. |\n| **Spy × Family** | Comedy, Slice of','2026-07-08 14:12:53'),(47,NULL,4,NULL,'user','menga saytda bor animeni tavsiya qil','2026-07-08 14:14:08'),(48,NULL,4,NULL,'assistant','Tushunarli! **UZDUB** saytida odatda eng ko‘p so‘ralgan, to‘liq tarjima qilingan va yuqori reytingli animelar ro‘yxatini quyidagicha taqdim etaman. Ular deyarli barchasi saytda **toplamlarda** yoki **qidiruv** orqali topiladi.\n\n---\n\n## 🔥 **UZDUB da doim topiladigan “Zarur ko‘rish” ro‘yxati**\n\n| Anime | Holat | Janrlar | Nima uchun ko‘rish kerak? |\n|-------|-------|---------|---------------------------|\n| **Attack on Titan** (Shingeki no Kyojin) | ✅ Yakunlandi (Final Season + The Final Chapters) | Action, Mystery, Dark Fantasy | Eng kuchli plot twistlar, epik janglar, falsafiy mazmun. |\n| **Fullmetal Alchem','2026-07-08 14:14:08'),(49,NULL,4,NULL,'user','salom','2026-07-08 14:16:06'),(50,NULL,4,NULL,'assistant','Salom! Yana bir bor salom! 😊\n\nSizga anime tavsiya qilishda davom etaymi? Yoki boshqa biror narsa haqida gaplashamizmi? \n\nAgar anime tavsiyasi kerak bo\'lsa, **janrini** aytsangiz (masalan: jangari, komediya, qo\'rqinchli yoki romantika), aynan sizga yoqadiganini topib beraman! 👇','2026-07-08 14:16:06'),(51,NULL,4,NULL,'user','salom','2026-07-08 14:18:18'),(52,NULL,4,NULL,'assistant','Okay, the user just said \"salom\" again. Let me check the history.\n\nFirst, they said \"salom\", I replied with a greeting and asked how I can help. Then they said \"hoy yaqshimisan\", I responded positively and asked how they are. They then asked for anime recommendations, and I gave a detailed list based on genres. They clarified they want recommendations from the UZDUB site specifically, so I provided a list of popular anime available there with details.\n\nNow they\'ve said \"salom\" two more times. Hmm, maybe they\'re testing if I\'m still here, or perhaps they\'re having connection issues and sending multiple greetings. Alternatively, they might be waiting for me to continue the conversation but aren\'t sure what to say next.\n\nSince they\'ve repeatedly said \"salom\", the safest approach is to keep it friendly and open-ended. I should acknowledge their greeting, express that I\'m here to help, and gently prompt them to specify what they need—whether it\'s more anime recs, something else, or just chatting.\n\nI notice they might be Uzbek-speaking, so I\'ll stick to Uzbek in my response. No need for complex sentences; keep it simple and warm. Maybe they\'re shy or unsure how to proceed, so an inviting tone is key.\n\nAlso, considering past interaction: they wanted UZDUB-specific anime recs, which I provided. If they\'re saying \"salom\" again, perhaps they didn\'t see my last reply? But in the history, my last message was the detailed UZDUB list. Wait, looking back:\n\n- User: \"anime tavsiya qil\" → I gave general recs\n- User: \"menga saytda bor animeni tavsiya qil\" → I gave UZDUB-specific list\n- Then user said \"salom\" twice in a row.\n\nAh, so after my UZDUB list reply,','2026-07-08 14:18:18'),(53,NULL,4,NULL,'user','salom','2026-07-10 06:02:10'),(54,NULL,4,NULL,'assistant','Salom! Men sizni kutayaptim. Qandaydir yordamga muhtoj bo\'lsangiz, menga xabar qiling!','2026-07-10 06:02:10'),(55,NULL,4,NULL,'user','menga kino tavsiya qil','2026-07-10 06:03:18'),(56,NULL,4,NULL,'assistant','Kino tavsiya qilaman? Men sizga bir nechta filmlarni tavsiya qilishni xohlaysizmi? \"Qora soqol\", \"Mening ismim Jon\", \"O\'g\'il\" kabi filmlar menenga','2026-07-10 06:03:18'),(57,NULL,4,NULL,'user','salom','2026-07-10 06:58:46'),(58,NULL,4,NULL,'assistant','Salom! Sizga kino tavsiya qilaman. \"Qora soqol\", \"Mening ismim Jon\", \"O\'g\'il\" kabi filmlar menenga ma\'lum. Yoki sizga yangi chiqqan filmlarni ko','2026-07-10 06:58:46'),(59,NULL,4,NULL,'user','davom et','2026-07-10 07:01:39'),(60,NULL,4,NULL,'assistant','Men sizga \"Qora soqol\", \"Mening ismim Jon\", \"O\'g\'il\" kabi filmlarni tavsiya qilaman. Shuningdek, \"Ko\'zlarimdan kechib ketgan\", \"Mening yigit','2026-07-10 07:01:39'),(61,NULL,4,NULL,'user','salom','2026-07-10 07:15:08'),(62,NULL,4,NULL,'assistant','Salom Doniyorbek! Men sizga \"Qora soqol\", \"Mening ismim Jon\", \"O\'g\'il\" kabi filmlarni tavsiya qilaman. Shuningdek, \"Ko\'zlarimdan kechib ket','2026-07-10 07:15:08'),(63,NULL,4,NULL,'user','qaleysan','2026-07-10 08:35:46'),(64,NULL,4,NULL,'assistant','Salom Doniyorbek! Men sizga \"Qora soqol\", \"Mening ismim Jon\", \"O\'g\'il\" kabi filmlarni tavsiya qilaman. Shuningdek, \"Ko\'zlarimdan kechib ket','2026-07-10 08:35:46'),(65,NULL,4,NULL,'user','salom','2026-07-11 05:34:21'),(66,NULL,4,NULL,'assistant','Salom Doniyorbek! Men sizga \"Qora soqol\", \"Mening ismim Jon\", \"O\'g\'il\" kabi filmlarni tavsiya qilaman. Shuningdek, \"Ko\'zlarimdan kechib ket','2026-07-11 05:34:21'),(67,NULL,4,NULL,'user','salom','2026-07-11 06:02:35'),(68,NULL,4,NULL,'assistant','Salom Doniyorbek! Men sizga \"Qora soqol\", \"Mening ismim Jon\", \"O\'g\'il\" kabi filmlarni tavsiya qilaman. Shuningdek, \"Ko\'zlarimdan kechib ket','2026-07-11 06:02:35'),(69,NULL,4,NULL,'user','salom','2026-07-11 06:03:23'),(70,NULL,4,NULL,'assistant','Salom Doniyorbek! Men sizga \"Qora soqol\", \"Mening ismim Jon\", \"O\'g\'il\" kabi filmlarni tavsiya qilaman. Shuningdek, \"Ko\'zlarimdan kechib ket','2026-07-11 06:03:23'),(71,NULL,4,NULL,'user','salom','2026-07-11 06:06:34'),(72,NULL,4,NULL,'assistant','Salom Doniyorbek! Men sizga \"Qora soqol\", \"Mening ismim Jon\", \"O\'g\'il\" kabi filmlarni tavsiya qilaman. Shuningdek, \"Ko\'zlarimdan kechib ket','2026-07-11 06:06:34'),(171,40,2,NULL,'user','menga kino tavsiya qil','2026-07-25 04:10:43'),(172,40,2,NULL,'assistant','Kino izlayotgan bo\'lsangiz, \"qwere\" (2022) filmi Drama, Komediya, Psixologik janrlarda bo\'lib, ★7.0 reytingga ega. Ko\'rish: /uzdub/watch.php?id=2 🎥','2026-07-25 04:10:43'),(173,40,2,NULL,'user','xabarlar bo\'limiga o\'t','2026-07-25 04:10:54'),(174,40,2,NULL,'assistant','\"Re zero\" (2026) anime seriyasi Drama, Psixologik, Isekai janrlarda bo\'lib, ★9.8 reytingga ega. Ko\'rish: /uzdub/watch.php?id=1 📺','2026-07-25 04:10:54'),(175,40,2,NULL,'user','xabarlar bo\'limiga o\'t','2026-07-25 04:11:22'),(176,40,2,NULL,'assistant','\"Re zero\" (2026) anime seriyasi Drama, Psixologik, Isekai janrlarda bo\'lib, ★9.8 reytingga ega. Ko\'rish: /uzdub/watch.php?id=1 📺','2026-07-25 04:11:22'),(177,40,2,NULL,'user','kino tavsiya qil','2026-07-25 04:11:31'),(178,40,2,NULL,'assistant','\"qwere\" (2022) filmi Drama, Komediya, Psixologik janrlarda bo\'lib, ★7.0 reytingga ega. Ko\'rish: /uzdub/watch.php?id=2 🎥','2026-07-25 04:11:31'),(189,41,3,NULL,'user','assalomu alaykom','2026-07-27 03:57:11'),(190,41,3,NULL,'assistant','Va alaykum assalom! \n\"Re zero\" (ID:1) anime seriali juda qiziqarli. Reytingi ham yuqori (★9.8). Ko\'rish: /uzdub/watch.php?id=1 😊','2026-07-27 03:57:11'),(203,48,5,NULL,'user','Men bu saytni Internetga vaqtinchalik chiqardim','2026-07-28 04:04:00'),(204,48,5,NULL,'assistant','\"Re zero\" animeni ko\'rishni tavsiya qilaman :)! Ko\'rish: /uzdub/watch.php?id=1','2026-07-28 04:04:00'),(227,58,4,NULL,'user','menga anime tavsiya qil','2026-08-05 05:26:13'),(228,58,4,NULL,'assistant','\"Re zero\" (ID:1) anime tavsiya qilaman :)! Ko\'rish: /uzdub/watch.php?id=1','2026-08-05 05:26:13'),(229,61,4,NULL,'user','Salon','2026-08-11 07:52:23'),(230,61,4,NULL,'assistant','Anime tavsiya qilaman :) \n\"Re zero\" (2026, ★9.8) - Subaru va uning hamrohlari Pristella Suv darvozasidagi jangda g\'alaba qozonishdi. \nKo\'rish: /uzdub/watch.php?id=1 🎥','2026-08-11 07:52:23'),(231,61,4,NULL,'user','nechasoniyadasenjavobberasan','2026-08-13 13:51:06'),(232,61,4,NULL,'assistant','Qo\'rqinchli filmni tavsiya qilaman :) \n\"Tug\'ilmagan\" (2009, Drama, Psixologik, Qo\'rqinchli, Horror, ★0.0) \nKo\'rish: http://localhost/uzdub/watch.php?id=42 🎃','2026-08-13 13:51:06'),(233,61,4,NULL,'user','Anime tavsiya qilaman :) \"Re zero\" (2026, ★9.8) - Subaru va uning hamrohlari Pristella Suv darvozasidagi jangda g\'alaba qozonishdi. Ko\'rish: /uzdub/watch.php?id=1 🎥usaytdayoqku','2026-08-13 13:51:37'),(234,61,4,NULL,'assistant','\"Re zero\" (2026, ★9.8) - Subaru va uning hamrohlari Pristella Suv darvozasidagi jangda g\'alaba qozonishdi. \nKo\'rish: http://localhost/uzdub/watch.php?id=1 🎥','2026-08-13 13:51:37');
/*!40000 ALTER TABLE `ai_chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_chat_sessions`
--

DROP TABLE IF EXISTS `ai_chat_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_chat_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT 'Yangi chat',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ai_chat_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_chat_sessions`
--

LOCK TABLES `ai_chat_sessions` WRITE;
/*!40000 ALTER TABLE `ai_chat_sessions` DISABLE KEYS */;
INSERT INTO `ai_chat_sessions` VALUES (40,2,'kino tavsiya qil','2026-07-25 04:10:31','2026-07-25 04:11:31'),(41,3,'assalomu alaykom','2026-07-27 03:57:02','2026-07-27 03:57:11'),(48,5,'Men bu saytni Internetga vaqti...','2026-07-28 04:03:25','2026-07-28 04:04:00'),(58,4,'menga anime tavsiya qil','2026-08-05 05:25:57','2026-08-05 05:26:13'),(59,4,'','2026-08-10 04:18:25','2026-08-10 04:18:25'),(60,4,'','2026-08-10 04:21:47','2026-08-10 04:21:47'),(61,4,'Anime tavsiya qilaman :) \"Re z...','2026-08-11 07:52:12','2026-08-13 13:51:37');
/*!40000 ALTER TABLE `ai_chat_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_conversations`
--

DROP TABLE IF EXISTS `ai_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(160) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ai_conversations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_conversations`
--

LOCK TABLES `ai_conversations` WRITE;
/*!40000 ALTER TABLE `ai_conversations` DISABLE KEYS */;
INSERT INTO `ai_conversations` VALUES (1,4,'salom','2026-07-06 04:54:55','2026-08-03 10:08:50'),(2,4,'salom','2026-07-06 04:55:25','2026-08-03 10:08:50'),(3,4,'salom','2026-07-06 04:56:01','2026-08-03 10:08:50'),(4,4,'salom','2026-07-06 05:19:25','2026-08-03 10:08:50'),(5,4,'salom','2026-07-06 05:48:05','2026-08-03 10:08:50'),(6,2,'salom','2026-07-06 06:02:34','2026-07-06 07:29:55');
/*!40000 ALTER TABLE `ai_conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_knowledge`
--

DROP TABLE IF EXISTS `ai_knowledge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_knowledge` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(160) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `use_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_knowledge`
--

LOCK TABLES `ai_knowledge` WRITE;
/*!40000 ALTER TABLE `ai_knowledge` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_knowledge` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_user_memory`
--

DROP TABLE IF EXISTS `ai_user_memory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_user_memory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `key_type` varchar(50) NOT NULL,
  `value_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_memory` (`user_id`,`key_type`),
  CONSTRAINT `ai_user_memory_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_user_memory`
--

LOCK TABLES `ai_user_memory` WRITE;
/*!40000 ALTER TABLE `ai_user_memory` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_user_memory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bot_admin_states`
--

DROP TABLE IF EXISTS `bot_admin_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bot_admin_states` (
  `chat_id` bigint(20) NOT NULL,
  `step` varchar(50) NOT NULL DEFAULT '',
  `data` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bot_admin_states`
--

LOCK TABLES `bot_admin_states` WRITE;
/*!40000 ALTER TABLE `bot_admin_states` DISABLE KEYS */;
/*!40000 ALTER TABLE `bot_admin_states` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Kino','kino'),(2,'Anime','anime'),(3,'Multfilm','multfilm');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_reactions`
--

DROP TABLE IF EXISTS `chat_reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_msg_user` (`message_id`,`user_id`),
  KEY `idx_message_id` (`message_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `chat_reactions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `global_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_reactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_reactions`
--

LOCK TABLES `chat_reactions` WRITE;
/*!40000 ALTER TABLE `chat_reactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_reactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `content_id` (`content_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content`
--

DROP TABLE IF EXISTS `content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `content_code` varchar(10) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `title_ru` varchar(255) DEFAULT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `title_jp` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `description_ru` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `poster` varchar(255) DEFAULT NULL,
  `banner_url` varchar(500) DEFAULT NULL,
  `poster_thumb` varchar(255) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `release_year` int(11) DEFAULT NULL,
  `season` int(11) DEFAULT 1,
  `total_episodes` int(11) DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT 0.0,
  `studio` varchar(255) DEFAULT NULL,
  `director` varchar(255) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `intro_start` int(11) NOT NULL DEFAULT 0,
  `intro_end` int(11) NOT NULL DEFAULT 0,
  `status` enum('ongoing','completed','upcoming') DEFAULT 'completed',
  `is_series` tinyint(1) DEFAULT 0,
  `is_premium` tinyint(1) DEFAULT 0,
  `video_type` enum('cloud','file','telegram','embed') NOT NULL DEFAULT 'cloud',
  `video_url` varchar(500) DEFAULT NULL,
  `trailer_url` varchar(500) DEFAULT NULL,
  `embed_code` text DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_code` (`content_code`),
  UNIQUE KEY `uniq_content_slug` (`slug`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `content_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content`
--

LOCK TABLES `content` WRITE;
/*!40000 ALTER TABLE `content` DISABLE KEYS */;
INSERT INTO `content` VALUES (42,NULL,'KN2972','Tug\'ilmagan','Нерожденный','Unborn',NULL,NULL,'Film Keysi Beldon (Odette Yustman) ismli talaba qiz atrofida aylanadi. Unga to\'satdan dahshatli tushlar, g\'alati sharpalar va moviy ko\'zli g\'olib bola ko\'rina boshlaydi. Shu bilan birga, Keysining jigarrang ko\'zlari asta-sekin ko\'k rangga o\'zgaradi','Фильм рассказывает о старшекласснице Кейси Белдон (Одетт Юстман). Внезапно ей начинают сниться кошмары, её начинают преследовать странные призраки и появляется голубоглазый мальчик. Одновременно с этим карие глаза Кейси постепенно меняют цвет на голубой.','The film revolves around a high school student named Casey Beldon (Odette Yustman). She suddenly begins to have nightmares, strange ghosts, and a blue-eyed boy. At the same time, Casey\'s brown eyes gradually change to blue.','https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSad2KWPp8I6q6oi3izqVnJDtnM_qeRL5veFaGyvYpHQQ&s',NULL,NULL,1,2009,1,NULL,0.0,NULL,NULL,'1 soat 30 daqiqa',0,0,'completed',0,0,'cloud','https://archive.org/download/uzdub-premyera-nomi-tug-ilmagan-tili-o-zbek-tilid-e1faf1/video.mp4',NULL,NULL,0,'2026-08-13 13:34:45',NULL,NULL,NULL);
/*!40000 ALTER TABLE `content` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_actors`
--

DROP TABLE IF EXISTS `content_actors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_actors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `content_id` (`content_id`),
  CONSTRAINT `content_actors_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_actors`
--

LOCK TABLES `content_actors` WRITE;
/*!40000 ALTER TABLE `content_actors` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_actors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_comments`
--

DROP TABLE IF EXISTS `content_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_content` (`content_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `content_comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `content_comments_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_comments`
--

LOCK TABLES `content_comments` WRITE;
/*!40000 ALTER TABLE `content_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_genres`
--

DROP TABLE IF EXISTS `content_genres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_genres` (
  `content_id` int(11) NOT NULL,
  `genre_id` int(11) NOT NULL,
  PRIMARY KEY (`content_id`,`genre_id`),
  KEY `genre_id` (`genre_id`),
  CONSTRAINT `content_genres_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
  CONSTRAINT `content_genres_ibfk_2` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_genres`
--

LOCK TABLES `content_genres` WRITE;
/*!40000 ALTER TABLE `content_genres` DISABLE KEYS */;
INSERT INTO `content_genres` VALUES (42,3),(42,6),(42,11),(42,18);
/*!40000 ALTER TABLE `content_genres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_ratings`
--

DROP TABLE IF EXISTS `content_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 10),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_content` (`user_id`,`content_id`),
  KEY `idx_content` (`content_id`),
  CONSTRAINT `content_ratings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `content_ratings_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_ratings`
--

LOCK TABLES `content_ratings` WRITE;
/*!40000 ALTER TABLE `content_ratings` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_ratings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_subtitles`
--

DROP TABLE IF EXISTS `content_subtitles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_subtitles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `episode_id` int(11) DEFAULT NULL,
  `language` varchar(10) NOT NULL DEFAULT 'uz',
  `label` varchar(50) DEFAULT 'O''zbek',
  `file_path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_content` (`content_id`),
  CONSTRAINT `content_subtitles_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_subtitles`
--

LOCK TABLES `content_subtitles` WRITE;
/*!40000 ALTER TABLE `content_subtitles` DISABLE KEYS */;
/*!40000 ALTER TABLE `content_subtitles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `episodes`
--

DROP TABLE IF EXISTS `episodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `episodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `season` int(11) DEFAULT 1,
  `episode_number` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `video_type` enum('cloud','file','telegram','embed') NOT NULL DEFAULT 'cloud',
  `video_url` varchar(500) NOT NULL,
  `video_url_1080p` varchar(500) DEFAULT NULL,
  `video_url_720p` varchar(500) DEFAULT NULL,
  `telegram_file_id` varchar(255) DEFAULT NULL,
  `embed_code` text DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `intro_start` int(11) NOT NULL DEFAULT 0,
  `intro_end` int(11) NOT NULL DEFAULT 0,
  `is_premium` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `content_id` (`content_id`),
  CONSTRAINT `episodes_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `episodes`
--

LOCK TABLES `episodes` WRITE;
/*!40000 ALTER TABLE `episodes` DISABLE KEYS */;
/*!40000 ALTER TABLE `episodes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `genres`
--

DROP TABLE IF EXISTS `genres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `genres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `color` varchar(7) DEFAULT '#7c4dff',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `genres`
--

LOCK TABLES `genres` WRITE;
/*!40000 ALTER TABLE `genres` DISABLE KEYS */;
INSERT INTO `genres` VALUES (1,'Fantastika','fantastika','#7c4dff'),(2,'Romantika','romantika','#e91e63'),(3,'Drama','drama','#ff5722'),(4,'Komediya','komediya','#ff9800'),(5,'Triller','triller','#f44336'),(6,'Psixologik','psixologik','#9c27b0'),(7,'Isekai','isekai','#00bcd4'),(8,'Sehrgar','sehrgar','#673ab7'),(9,'Sarguzasht','sarguzasht','#4caf50'),(10,'Sinchi','sinchi','#607d8b'),(11,'Qo\'rqinchli','qorqinchli','#d32f2f'),(12,'Harbiy','harbiy','#795548'),(13,'Hayotiy','hayotiy','#8bc34a'),(14,'Tarixiy','tarixiy','#afb42b'),(15,'Sport','sport','#009688'),(16,'Action','action','#7c4dff'),(17,'Comedy','comedy','#7c4dff'),(18,'Horror','horror','#7c4dff'),(19,'Sci-Fi','sci-fi','#7c4dff'),(20,'Romance','romance','#7c4dff');
/*!40000 ALTER TABLE `genres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `global_messages`
--

DROP TABLE IF EXISTS `global_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `global_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `category` enum('kino','anime','multfilm') NOT NULL DEFAULT 'kino',
  `message` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `attachment_type` enum('image','gif') DEFAULT NULL,
  `reply_to` int(11) DEFAULT NULL,
  `forwarded_from` int(11) DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_edited` tinyint(1) DEFAULT 0,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `global_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `global_messages`
--

LOCK TABLES `global_messages` WRITE;
/*!40000 ALTER TABLE `global_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `global_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `likes`
--

DROP TABLE IF EXISTS `likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content_id` int(11) DEFAULT NULL,
  `comment_id` int(11) DEFAULT NULL,
  `type` enum('like','dislike') NOT NULL DEFAULT 'like',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_comment` (`user_id`,`comment_id`),
  KEY `content_id` (`content_id`),
  KEY `comment_id` (`comment_id`),
  CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
  CONSTRAINT `likes_ibfk_3` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `likes`
--

LOCK TABLES `likes` WRITE;
/*!40000 ALTER TABLE `likes` DISABLE KEYS */;
/*!40000 ALTER TABLE `likes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_approvals`
--

DROP TABLE IF EXISTS `login_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `status` enum('pending','approved','denied','expired') NOT NULL DEFAULT 'pending',
  `ip_address` varchar(45) DEFAULT '',
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `login_approvals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_approvals`
--

LOCK TABLES `login_approvals` WRITE;
/*!40000 ALTER TABLE `login_approvals` DISABLE KEYS */;
INSERT INTO `login_approvals` VALUES (2,4,'3e81c52c292cf750468d08d5f0142552','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-01 17:08:13','2026-08-01 14:11:13','2026-08-01 17:08:39'),(3,4,'08a5d817fae6790432a9968a5322c4d5','denied','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-01 17:12:38','2026-08-01 14:15:38','2026-08-01 17:13:13'),(5,4,'eb553a20477417a49d4aab745103c3e3','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 09:29:41','2026-08-03 06:32:41','2026-08-03 09:29:49'),(6,4,'5dfaaeb0cd908684f7aea6fbd6de3ed1','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 09:31:50','2026-08-03 06:34:50','2026-08-03 09:33:50'),(7,4,'d6dd2a2aa374efeb595e777e480dced3','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 10:19:35','2026-08-03 10:22:35','2026-08-03 10:19:43'),(8,4,'05f1ecf74b92378f4658a2d6abf4d0bf','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 10:44:32','2026-08-03 10:47:32','2026-08-03 10:44:38'),(9,4,'316bdd02d0e18c1ab6643ab70e80aba1','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 10:48:17','2026-08-03 10:51:17','2026-08-03 10:48:44'),(10,4,'a2e4b51d743d650d97066fa29011fc51','denied','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 10:49:07','2026-08-03 10:52:07','2026-08-03 10:49:16'),(11,4,'6e83c80c201ba93f4bb3d22d261cc8d5','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 10:49:25','2026-08-03 10:52:25','2026-08-03 10:49:28'),(12,4,'b152974c67ef5f6af7abe47e67e226b5','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 11:20:23','2026-08-03 11:23:23','2026-08-03 11:20:35'),(13,4,'deda6f1e12481e8dd614cbadbfffb80e','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 11:23:22','2026-08-03 11:26:22','2026-08-03 11:23:26'),(14,4,'b96c2041cb7706ada50c6d9d3ae9c527','expired','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 15:08:55','2026-08-03 15:11:55',NULL),(15,4,'f2e5b46014168c41b71127148b8d34cf','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 15:09:01','2026-08-03 15:12:01','2026-08-03 15:09:40'),(16,4,'2125788656dd5d836c9e2cd886e3f100','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 15:09:46','2026-08-03 15:12:46','2026-08-03 15:09:51'),(17,4,'d6d63d55301dad04399fde720c7bcfde','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 15:11:11','2026-08-03 15:14:11','2026-08-03 15:11:15'),(18,4,'9a511e6f9e039c95f54e6702af44222b','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-03 15:15:38','2026-08-03 15:18:38','2026-08-03 15:15:41'),(19,4,'ec871d45f6d1b0b5cf3e674ba0badd88','expired','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-04 09:54:32','2026-08-04 09:57:32',NULL),(20,4,'d5b8fbc2914a8b150faca28f58ef26d6','expired','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-05 09:33:11','2026-08-05 09:36:11',NULL),(21,4,'ba5d6c96c8c7b5f76a09a6cc1aa3354b','expired','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-05 10:18:17','2026-08-05 10:21:17',NULL),(22,4,'85eb76c74bcda53a139e86d3ff200bf4','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-05 10:24:07','2026-08-05 10:27:07','2026-08-05 10:24:29'),(23,4,'a76e1bfd2d8b1fc4ac6fd621fb2ba073','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-08-05 11:03:12','2026-08-05 11:06:12','2026-08-05 11:03:21'),(24,4,'ce7764836c8dd00949ad2fcd9d5b2824','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-06 09:45:01','2026-08-06 09:48:01','2026-08-06 09:45:12'),(25,4,'519093ce36487ddf89b4cc6b85f3b1bc','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 11:33:12','2026-08-07 11:36:12','2026-08-07 11:35:05'),(26,4,'2eb77b91e5c9d954987d9599e8b14450','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 12:34:40','2026-08-07 12:37:40','2026-08-07 12:34:44'),(27,4,'2a83c4755205e3343ae242a2881bdc5b','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-08 10:37:50','2026-08-08 10:40:50','2026-08-08 10:37:54'),(28,4,'c7839f496f293b0365cc75ffe524759a','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-08 12:15:48','2026-08-08 12:18:48','2026-08-08 12:15:53'),(29,4,'51702f3de02a13becbcea3d70a5cf96c','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-10 09:13:31','2026-08-10 09:16:31','2026-08-10 09:15:06'),(30,4,'620677cb3c0a88e4dd6ce217c919a9ed','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-10 09:15:28','2026-08-10 09:18:28','2026-08-10 09:15:32'),(31,4,'e29c76128504fccf1df1b0fbaf072ef0','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-10 09:21:08','2026-08-10 09:24:08','2026-08-10 09:21:12'),(34,4,'880cf05b57837c3e329d5f0f5c353f34','expired','127.0.0.1','okhttp/4.12.0','2026-08-11 09:11:06','2026-08-11 09:14:06',NULL),(35,4,'7ed513a0da76d14c76902b709c3f7fe3','expired','127.0.0.1','okhttp/4.12.0','2026-08-11 09:11:11','2026-08-11 09:14:11',NULL),(36,4,'30413f49fbc4906695530fdbb871f21d','expired','127.0.0.1','okhttp/4.12.0','2026-08-11 09:15:33','2026-08-11 09:18:33',NULL),(37,4,'c290710a4e488292b5cafd53b8befc1e','expired','127.0.0.1','okhttp/4.12.0','2026-08-11 09:17:40','2026-08-11 09:20:40',NULL),(38,4,'86bcc57846ff81e96b578faaf7a5815a','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-11 09:33:13','2026-08-11 09:36:13','2026-08-11 09:33:24'),(39,1026,'d967538efe2c5dd6f4e3817565dc1319','expired','::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; ru-RU) WindowsPowerShell/5.1.22621.963','2026-08-11 10:32:40','2026-08-11 10:35:40',NULL),(40,1026,'e5c8f3f2e1cd7a73586e771debb5f2a8','approved','127.0.0.1','okhttp/4.12.0','2026-08-11 10:34:34','2026-08-11 10:37:34','2026-08-11 10:36:27'),(41,4,'f98bb7799b064eb29b06326cbee7c804','approved','127.0.0.1','okhttp/4.12.0','2026-08-11 12:31:59','2026-08-11 12:34:59','2026-08-11 12:32:13'),(42,4,'ad842f0bc52212e9961404ab52cc5eda','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-12 09:16:04','2026-08-12 09:19:04','2026-08-12 09:16:16'),(43,4,'f1f32512483c0b82352ecd3907a3b8e5','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-13 09:39:20','2026-08-13 09:42:20','2026-08-13 09:39:31'),(44,4,'0f6e368be21c3476d35bc9717fbfc380','approved','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-13 09:41:08','2026-08-13 09:44:08','2026-08-13 09:41:13');
/*!40000 ALTER TABLE `login_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(191) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_identifier_time` (`identifier`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (5,'admin:127.0.0.1:doniyorbek1','2026-07-28 03:45:54'),(1,'admin:::1:doniyorbek','2026-07-18 06:53:42'),(14,'admin:::1:doniyorbek','2026-08-03 09:27:53'),(10,'admin:::1:doniyorbek1','2026-07-30 04:36:42'),(12,'user:127.0.0.1:testlogin_1016','2026-08-01 12:23:07'),(15,'user:::1:','2026-08-08 10:49:25'),(16,'user:::1:','2026-08-08 10:49:33'),(17,'user:::1:','2026-08-08 10:49:39'),(6,'user:::1:centerit260@gmail.com','2026-07-28 04:01:09'),(9,'user:::1:doniyorbek10','2026-07-29 05:00:34');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('like','comment','message','system','premium') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `premium_payments`
--

DROP TABLE IF EXISTS `premium_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `premium_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan` enum('1month','3month','1year') NOT NULL,
  `amount` int(11) NOT NULL,
  `screenshot` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `premium_payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `premium_payments`
--

LOCK TABLES `premium_payments` WRITE;
/*!40000 ALTER TABLE `premium_payments` DISABLE KEYS */;
INSERT INTO `premium_payments` VALUES (1,4,'3month',25000,'f_6a4b4234277d59.37556733.png','rejected','2026-10-04 07:50:44','2026-07-06 05:50:44'),(2,4,'3month',25000,'f_6a4b4247b943f9.61901766.webp','rejected','2026-10-04 07:51:03','2026-07-06 05:51:03'),(3,2,'3month',0,NULL,'approved','2026-10-04 08:02:18','2026-07-06 06:02:18'),(4,4,'1year',80000,'f_6a4b581e5a2217.30014137.webp','rejected','2027-07-06 09:24:14','2026-07-06 07:24:14'),(5,4,'3month',25000,'f_6a4b84d4aee698.23421444.webp','approved','2026-10-04 12:35:31','2026-07-06 10:35:00'),(6,4,'1month',0,NULL,'approved','2026-08-06 09:01:02','2026-07-07 07:01:02'),(7,4,'1year',0,NULL,'approved','2027-07-07 09:02:11','2026-07-07 07:02:11'),(8,2,'1month',10000,'f_6a5da4dde306b9.30433212.jpeg','approved','2026-08-20 13:24:28','2026-07-20 04:32:29'),(9,3,'3month',0,NULL,'approved','2026-10-19 13:38:30','2026-07-21 11:38:30'),(10,4,'3month',25000,'f_6a66edfce7d136.89242463.webp','approved','2026-10-25 07:35:08','2026-07-27 05:34:52'),(11,4,'3month',25000,'f_6a66ee1032b889.67491925.webp','rejected','2026-10-25 07:35:12','2026-07-27 05:35:12'),(12,5,'3month',25000,'f_6a68293610edc7.31168909.jpg','approved','2026-10-26 06:03:03','2026-07-28 03:59:50'),(13,4,'3month',25000,'f_6a79512c5f0617.66112797.png','rejected','2026-11-08 09:18:52','2026-08-10 04:18:52'),(14,4,'3month',25000,'f_6a79512e962df0.61885660.png','approved','2026-11-08 09:19:57','2026-08-10 04:18:54'),(15,6,'3month',0,NULL,'approved','2026-11-08 09:20:20','2026-08-10 04:20:20');
/*!40000 ALTER TABLE `premium_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `private_messages`
--

DROP TABLE IF EXISTS `private_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `private_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `attachment_type` enum('image','gif') DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `private_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `private_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `private_messages`
--

LOCK TABLES `private_messages` WRITE;
/*!40000 ALTER TABLE `private_messages` DISABLE KEYS */;
INSERT INTO `private_messages` VALUES (1,2,4,'salom',NULL,NULL,1,'2026-07-06 05:20:56'),(2,4,2,'qaleysan',NULL,NULL,1,'2026-07-06 05:21:08'),(3,4,2,'💯',NULL,NULL,1,'2026-07-06 05:21:13'),(4,4,4,'salom',NULL,NULL,1,'2026-07-21 12:08:50'),(5,4,4,'qalaysan',NULL,NULL,1,'2026-07-21 12:09:04'),(6,4,4,'😂',NULL,NULL,1,'2026-07-21 12:09:15'),(7,4,4,'🙌',NULL,NULL,1,'2026-07-27 05:32:07'),(8,4,4,'😀😡',NULL,NULL,1,'2026-07-27 05:32:15'),(9,4,4,'🎬⭐❤️🍿',NULL,NULL,1,'2026-07-27 05:32:20'),(10,4,4,'🙌',NULL,NULL,0,'2026-07-29 04:00:23'),(11,4,2,'qewq',NULL,NULL,0,'2026-08-05 05:33:17'),(12,4,2,'eqweq2',NULL,NULL,0,'2026-08-05 05:33:19'),(13,4,2,'2qe',NULL,NULL,0,'2026-08-05 05:33:19'),(14,4,2,'q2e',NULL,NULL,0,'2026-08-05 05:33:19'),(15,4,2,'wq',NULL,NULL,0,'2026-08-05 05:33:20'),(16,4,2,'2e',NULL,NULL,0,'2026-08-05 05:33:20'),(17,4,2,'w',NULL,NULL,0,'2026-08-05 05:33:20'),(18,4,2,'q',NULL,NULL,0,'2026-08-05 05:33:20'),(19,4,2,'2e',NULL,NULL,0,'2026-08-05 05:33:21'),(20,4,2,'w',NULL,NULL,0,'2026-08-05 05:33:21'),(21,4,2,'qe',NULL,NULL,0,'2026-08-05 05:33:21'),(22,4,2,'wq',NULL,NULL,0,'2026-08-05 05:33:21'),(23,4,2,'2',NULL,NULL,0,'2026-08-05 05:33:22'),(24,4,2,'wq',NULL,NULL,0,'2026-08-05 05:33:22'),(25,4,2,'2',NULL,NULL,0,'2026-08-05 05:33:22'),(26,4,2,'ewq2e',NULL,NULL,0,'2026-08-05 05:33:24'),(27,4,2,'💯',NULL,NULL,0,'2026-08-05 05:33:28');
/*!40000 ALTER TABLE `private_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promo_codes`
--

DROP TABLE IF EXISTS `promo_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promo_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `discount_percent` int(11) DEFAULT 0,
  `free_days` int(11) DEFAULT 0,
  `max_uses` int(11) DEFAULT 1,
  `used_count` int(11) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promo_codes`
--

LOCK TABLES `promo_codes` WRITE;
/*!40000 ALTER TABLE `promo_codes` DISABLE KEYS */;
INSERT INTO `promo_codes` VALUES (1,'UZDUBPLATFORM2026',0,7,100,0,'2027-12-31 23:59:59',1,'2026-07-27 05:26:02');
/*!40000 ALTER TABLE `promo_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promo_redemptions`
--

DROP TABLE IF EXISTS `promo_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promo_redemptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_promo_user` (`promo_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `promo_redemptions_ibfk_1` FOREIGN KEY (`promo_id`) REFERENCES `promo_codes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promo_redemptions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promo_redemptions`
--

LOCK TABLES `promo_redemptions` WRITE;
/*!40000 ALTER TABLE `promo_redemptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `promo_redemptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(191) NOT NULL,
  `endpoint` varchar(64) NOT NULL,
  `hit_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rate` (`identifier`,`endpoint`,`hit_at`)
) ENGINE=InnoDB AUTO_INCREMENT=913 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
INSERT INTO `rate_limits` VALUES (902,'::1:save-progress','save-progress','2026-08-13 05:06:24'),(903,'::1:save-progress','save-progress','2026-08-13 05:06:24'),(904,'::1:save-progress','save-progress','2026-08-13 05:06:28'),(905,'::1:save-progress','save-progress','2026-08-13 05:06:29'),(906,'::1:save-progress','save-progress','2026-08-13 05:06:29'),(907,'::1:save-progress','save-progress','2026-08-13 05:19:35'),(908,'::1:save-progress','save-progress','2026-08-13 05:19:36'),(909,'::1:save-progress','save-progress','2026-08-13 05:19:36'),(910,'::1:save-progress','save-progress','2026-08-13 05:26:16'),(911,'::1:save-progress','save-progress','2026-08-13 05:26:19'),(912,'::1:save-progress','save-progress','2026-08-13 05:26:19');
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ratings`
--

DROP TABLE IF EXISTS `ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 10),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rating` (`content_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ratings`
--

LOCK TABLES `ratings` WRITE;
/*!40000 ALTER TABLE `ratings` DISABLE KEYS */;
/*!40000 ALTER TABLE `ratings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `related_content`
--

DROP TABLE IF EXISTS `related_content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `related_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `related_id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT 'similar',
  PRIMARY KEY (`id`),
  KEY `content_id` (`content_id`),
  KEY `related_id` (`related_id`),
  CONSTRAINT `related_content_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
  CONSTRAINT `related_content_ibfk_2` FOREIGN KEY (`related_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `related_content`
--

LOCK TABLES `related_content` WRITE;
/*!40000 ALTER TABLE `related_content` DISABLE KEYS */;
/*!40000 ALTER TABLE `related_content` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_content_status`
--

DROP TABLE IF EXISTS `user_content_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_content_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `status` enum('watching','planned','completed','paused','dropped','favorite') NOT NULL DEFAULT 'watching',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_content_status` (`user_id`,`content_id`),
  KEY `content_id` (`content_id`),
  KEY `idx_user_status` (`user_id`,`status`),
  CONSTRAINT `user_content_status_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_content_status_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=131 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_content_status`
--

LOCK TABLES `user_content_status` WRITE;
/*!40000 ALTER TABLE `user_content_status` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_content_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `user_agent` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `last_activity` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=140 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES (11,5,'nm6vsi9p487a09m3386d2lad5e','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36','::1','2026-07-28 09:04:28'),(17,2,'ntfekhmqpkjmh3e0j76pvt4q5o','Mozilla/5.0 (Linux; Android 15; NLA-LX2P Build/HONORNLA-L32P; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/150.0.7871.124 Mobile Safari/537.36','::1','2026-07-29 10:06:37'),(29,1008,'22gr8tje8ubmk9v1nnsvs7bj7p','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','::1','2026-08-01 16:57:41'),(82,1019,'1tg3tojqck7gvervr9sae8oijo','curl/7.83.1','127.0.0.1','2026-08-03 10:28:02'),(83,1019,'ume9413d9v0b7ucdj9lhhubjpk','curl/7.83.1','127.0.0.1','2026-08-03 10:28:13'),(99,3,'jl9abihir374hrlvcabi1hog42','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','::1','2026-08-05 18:44:42'),(100,2,'testwall492357110','curl/7.83.1','127.0.0.1','2026-08-05 14:53:59'),(103,3,'0123456789abcdefghijklmnop','curl/7.83.1','127.0.0.1','2026-08-06 10:06:51'),(110,2,'d8c48luhhktefho8blhhd81otb','Mozilla/5.0','::1','2026-08-08 15:28:13'),(117,1025,'fc1kth77fdqci453bu4r0pg5ut','okhttp/4.12.0','127.0.0.1','2026-08-11 10:16:48'),(118,1025,'a209o8obahla5g7k9r8t3pbmfb','okhttp/4.12.0','127.0.0.1','2026-08-11 10:20:06'),(119,1025,'j3a2fp0sfjrqe8v43q3k13s18q','okhttp/4.12.0','127.0.0.1','2026-08-11 10:23:01'),(120,1025,'j657tjgdon50s9klck0qr251l3','Mozilla/5.0 (Windows NT; Windows NT 10.0; ru-RU) WindowsPowerShell/5.1.22621.963','::1','2026-08-11 10:23:52'),(121,1025,'qm5tuekqppfmc054mol8kp52du','okhttp/4.12.0','127.0.0.1','2026-08-11 10:31:24'),(122,1026,'0qp5flldddqob6fcft8uq9t0br','okhttp/4.12.0','127.0.0.1','2026-08-11 10:36:28'),(123,4,'brt43b3freji9cc161uogciqun','okhttp/4.12.0','127.0.0.1','2026-08-11 12:32:14'),(125,6,'4pk6htetruedk6pm3a4ik55scm','Mozilla/5.0 (Linux; Android 11; sdk_gphone_x86) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Mobile Safari/537.36','127.0.0.1','2026-08-11 17:42:06'),(128,4,'n7ba1s0ef1kf9ufakdo2imbv6p','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','::1','2026-08-13 19:01:16');
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `theme` varchar(20) DEFAULT 'light',
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `privacy_profile` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_settings`
--

LOCK TABLES `user_settings` WRITE;
/*!40000 ALTER TABLE `user_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(8) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `is_premium` tinyint(1) DEFAULT 0,
  `premium_expires_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `online_time` int(11) DEFAULT 0,
  `is_email_verified` tinyint(1) DEFAULT 1,
  `email_verification_code` varchar(6) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `telegram_chat_id` varchar(32) DEFAULT NULL,
  `telegram_phone` varchar(32) DEFAULT NULL,
  `telegram_user_id` varchar(64) DEFAULT NULL,
  `tg_link_code` varchar(8) DEFAULT NULL,
  `tg_link_expires` datetime DEFAULT NULL,
  `tg_verify_code` varchar(8) DEFAULT NULL,
  `tg_verify_expires` datetime DEFAULT NULL,
  `username_changed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=1031 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (2,'47708832','123456','doniyorbekortiqboyev2010@gmail.com','$2y$10$5STw0/teqUYMsWOAtzF70.tvdZTK485VMSzfJkA387DR6N3jCCXAi',NULL,NULL,1,'2026-08-20 13:24:28','2026-08-05 11:22:29','110430589809477027147',NULL,'2026-08-05 18:44:40','2026-07-06 05:20:36',141051,1,NULL,1,NULL,'8555328179','998334410109','8555328179',NULL,NULL,NULL,NULL,NULL),(3,'23731416','IT CENTER','centerit260@gmail.com','$2y$10$2y80dg0jCPaWSlIPiv7MZ.QEd/SNIa3Vn9l.wOcFDHZrNdZVgogz6','f_6a6462f782c131.75895139.jpg',NULL,1,'2026-10-19 13:38:30','2026-08-05 11:55:01','118215979246391602421',NULL,'2026-08-05 18:44:42','2026-07-21 11:36:51',72500,1,NULL,0,NULL,'8916892011','998337258389','8916892011','6049F31A','2026-08-05 18:45:26','935881','2026-08-05 18:43:26',NULL),(4,'27200295','Doniyorbek','artikbayevichi@gmail.com','$2y$10$NPsxbNZqtJoeP.uoE0DIduOtrhUbZ9hrVa9NU6w4lUWekW.FGWhay','f_6a6dd89781f323.43537591.jpeg',NULL,1,'2026-11-08 09:19:57','2026-08-13 09:41:13','105072482429920725678',NULL,'2026-08-13 19:01:16','2026-07-21 12:08:14',445961,1,NULL,1,NULL,'8916892011','998337258389','8916892011',NULL,NULL,NULL,NULL,'2026-07-28 10:18:54'),(5,'77972773','doniyorbek0998','giyosegamov4@gmail.com','$2y$10$EaNwKTUZmSk7zMM7OxXyLe9qwmT54hF2gASr5Q/x.sNlmEHu8zCse',NULL,NULL,1,'2026-10-26 06:03:03','2026-07-28 08:57:40',NULL,NULL,'2026-07-28 09:04:28','2026-07-28 03:57:40',253,1,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(6,'','qwerty','mangituzdubno1@gmail.com','$2y$10$mYlKQZKl8XakEU.JjJAgZunmnrQP5EAqosqygDLvGlgOk.EhvhB/a',NULL,NULL,0,NULL,'2026-08-11 16:15:24',NULL,NULL,'2026-08-11 17:42:06','2026-07-29 10:24:28',3498,1,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1008,'63232816','ali','doniawdnanwduiab@gmail.com','$2y$10$qYWE0ehG5REjG0wUY8tB5.E5Q10//TjpCS1YfKB3Tt5Jbf6Rbtx42','f_6a6ddef9451742.26305568.png',NULL,0,NULL,'2026-08-01 16:57:41',NULL,NULL,'2026-08-01 16:57:21','2026-08-01 11:56:04',65,1,NULL,0,NULL,NULL,NULL,NULL,'6A02A184','2026-08-01 17:02:00',NULL,NULL,NULL),(1019,'76453053','test10546','test10546@test.uz','$2y$10$d8mM5uTAwE0vwB.edfHuwu8CV29my9DOaEosGUieDVvKPTuZWWRJK',NULL,NULL,0,NULL,'2026-08-03 10:28:13',NULL,NULL,NULL,'2026-08-03 05:28:02',0,1,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1024,'ST7F4820','simpletest','simpletest@test.uz','$2y$10$kc/knK74gaXWH52vP96LauRGzpWXuNHRGmzE/Ad2iskNdUKr1SxT6',NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,'2026-08-11 04:21:34',0,1,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1025,'TTAAF54F','totptest','totptest@test.uz','$2y$10$NUQWxVjWVBFyIS.fxhpUxeWsFCSxZtt0KqqDDtFwXxCiFM5Ez1Myq',NULL,NULL,0,NULL,'2026-08-11 10:31:24',NULL,NULL,NULL,'2026-08-11 04:40:07',0,1,NULL,1,'R4UNIDEHHSFKCNOSDR4U',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1026,'APAFC05F','approvaltest','approvaltest@test.uz','$2y$10$FsmLX5AGb/4AF5uGC1.xfOTc03bZGD91mHRZSQWnU0GOiL0U8PXw6',NULL,NULL,0,NULL,'2026-08-11 10:36:28',NULL,NULL,NULL,'2026-08-11 05:32:35',0,1,NULL,1,NULL,'8916892011',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(1028,'TST00001','testuser','test@test.uz','$2y$10$vxafCb9QgdB57fBpqcnx9e1ZHofYV/9GLVDQ0Ia.PcBqIauJ2e8YW',NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,'2026-08-12 04:35:40',0,1,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `video_source_log`
--

DROP TABLE IF EXISTS `video_source_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_source_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `episode_id` int(11) NOT NULL DEFAULT 0,
  `content_id` int(11) NOT NULL DEFAULT 0,
  `source_type` varchar(20) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `event` varchar(20) NOT NULL,
  `detail` text DEFAULT NULL,
  `notified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `episode_id` (`episode_id`),
  KEY `content_id` (`content_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `video_source_log`
--

LOCK TABLES `video_source_log` WRITE;
/*!40000 ALTER TABLE `video_source_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `video_source_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `video_source_state`
--

DROP TABLE IF EXISTS `video_source_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_source_state` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `episode_id` int(11) NOT NULL DEFAULT 0,
  `content_id` int(11) NOT NULL DEFAULT 0,
  `source_type` varchar(20) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `hls` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'unknown',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ep_content` (`episode_id`,`content_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `video_source_state`
--

LOCK TABLES `video_source_state` WRITE;
/*!40000 ALTER TABLE `video_source_state` DISABLE KEYS */;
INSERT INTO `video_source_state` VALUES (1,22,39,'rutube','https://rutube.ru/video/463609910/',NULL,'broken','2026-08-08 10:43:01'),(2,24,39,'vk','https://vkvideo.ru/video_ext.php?oid=1125439427&id=456239024&hash=524dfc79e1aeb849','https://vkvd360.okcdn.ru/?srcIp=213.230.86.234&pr=40&expires=1786647179828&srcAg=CHROME&fromCache=1&ms=178.237.23.253&type=3&sig=GBaRuHrXk0E&ct=0&urls=185.180.203.183&clientType=13&appId=512000384397&id=19235885746883','ok','2026-08-08 11:19:39'),(3,29,22,'rumble','https://rumble.com/v7dvvd6-u-qiz-yolgiz1-qism.html','https://1a-1791.com/video/fww1/52/s8/2/k/M/L/N/kMLNA.gaa.tar?r_file=chunklist.m3u8&r_type=application%2Fvnd.apple.mpegurl&r_range=201768448-201776034','ok','2026-08-08 10:43:18'),(4,0,34,'vk','https://vkvideo.ru/video-137380435_456241006','https://vkvd758.okcdn.ru/?srcIp=213.230.86.234&pr=40&expires=1786647185186&srcAg=CHROME&fromCache=1&ms=95.163.35.163&type=5&subId=19084124359263&sig=FMCVcmNL77Y&ct=0&urls=176.112.172.138&clientType=13&appId=512000384397&id=19084124359263','ok','2026-08-08 11:19:45'),(5,0,35,'vk','https://vkvideo.ru/video_ext.php?oid=1125439427&id=456239020&hash=1d88a7e0fbd30e40','https://vkvd580.okcdn.ru/?srcIp=213.230.86.234&pr=40&expires=1786647186421&srcAg=CHROME&fromCache=1&ms=185.226.55.229&type=3&sig=BPsCkWnxk0E&ct=0&urls=178.237.23.210&clientType=13&appId=512000384397&id=19228576385731','ok','2026-08-08 11:19:46'),(6,0,36,'rutube','https://rutube.ru/video/3eac3b4561676c17df9132a9a1e62e3e/','https://river-4-465.rtbcdn.ru/hls-vod/2_U8vRwrbfB2gC2M1o_eLg/1786792787/1774/0x5000c500c91dfbf4/5a9f8cc8041e452d975fa7fd00da3b2e.mp4.m3u8?i=480x368_662','ok','2026-08-08 11:19:47'),(7,0,38,'rutube','https://rutube.ru/video/463604879/','https://river-4-438.rtbcdn.ru/hls-vod/-6C4jZ8dByzA8zRjNX0amQ/1786792789/2616/0x5000039ce8382ffd/d10bfdb591bb4899ae8fdeb9d542d4b3.mp4.m3u8?i=136x240_435','ok','2026-08-08 11:19:48'),(8,30,22,'rumble','https://rumble.com/v7dvvd6-u-qiz-yolgiz1-qism.html','https://1a-1791.com/video/fww1/52/s8/2/k/M/L/N/kMLNA.gaa.tar?r_file=chunklist.m3u8&r_type=application%2Fvnd.apple.mpegurl&r_range=201768448-201776034','ok','2026-08-08 11:19:43');
/*!40000 ALTER TABLE `video_source_state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `watch_history`
--

DROP TABLE IF EXISTS `watch_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `watch_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `episode_id` int(11) NOT NULL DEFAULT 0,
  `watched_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `progress_seconds` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_content` (`user_id`,`content_id`,`episode_id`),
  KEY `content_id` (`content_id`),
  KEY `idx_user_history` (`user_id`,`watched_at`),
  KEY `idx_episode` (`episode_id`),
  CONSTRAINT `watch_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `watch_history_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1335 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `watch_history`
--

LOCK TABLES `watch_history` WRITE;
/*!40000 ALTER TABLE `watch_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `watch_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `watch_progress`
--

DROP TABLE IF EXISTS `watch_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `watch_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `episode_id` int(11) NOT NULL DEFAULT 0,
  `position_seconds` int(11) DEFAULT 0,
  `duration_seconds` int(11) DEFAULT 0,
  `is_completed` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_content` (`user_id`,`content_id`,`episode_id`),
  KEY `content_id` (`content_id`),
  KEY `idx_user_updated` (`user_id`,`updated_at`),
  KEY `idx_episode` (`episode_id`),
  CONSTRAINT `watch_progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `watch_progress_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1219 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `watch_progress`
--

LOCK TABLES `watch_progress` WRITE;
/*!40000 ALTER TABLE `watch_progress` DISABLE KEYS */;
/*!40000 ALTER TABLE `watch_progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `watched_content`
--

DROP TABLE IF EXISTS `watched_content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `watched_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `episode_id` int(11) NOT NULL DEFAULT 0,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_content` (`user_id`,`content_id`,`episode_id`),
  KEY `content_id` (`content_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `watched_content_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `watched_content_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `watched_content`
--

LOCK TABLES `watched_content` WRITE;
/*!40000 ALTER TABLE `watched_content` DISABLE KEYS */;
/*!40000 ALTER TABLE `watched_content` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `watchlist`
--

DROP TABLE IF EXISTS `watchlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `watchlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_watch` (`user_id`,`content_id`),
  KEY `content_id` (`content_id`),
  CONSTRAINT `watchlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `watchlist_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `watchlist`
--

LOCK TABLES `watchlist` WRITE;
/*!40000 ALTER TABLE `watchlist` DISABLE KEYS */;
/*!40000 ALTER TABLE `watchlist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'uzdub'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-14  9:16:54
