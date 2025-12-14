-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: admin
-- ------------------------------------------------------
-- Server version	5.7.44-log

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
-- Table structure for table `answers`
--

DROP TABLE IF EXISTS `answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `answer_text` text,
  `score` decimal(5,2) DEFAULT NULL,
  `is_auto_graded` tinyint(1) DEFAULT '0',
  `graded_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_answer` (`exam_id`,`question_id`,`student_id`),
  KEY `exam_id` (`exam_id`),
  KEY `question_id` (`question_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `answers_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `answers_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `answers`
--

LOCK TABLES `answers` WRITE;
/*!40000 ALTER TABLE `answers` DISABLE KEYS */;
/*!40000 ALTER TABLE `answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '班级名称',
  `description` text COMMENT '班级描述',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (2,'25级计算机1班','','2025-12-04 00:05:53'),(3,'25级计算机2班','','2025-12-04 14:38:05');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exams`
--

DROP TABLE IF EXISTS `exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text,
  `type` enum('exam','homework') NOT NULL DEFAULT 'exam',
  `teacher_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `duration` int(11) DEFAULT NULL COMMENT '考试时长（分钟）',
  `allow_retake` tinyint(1) DEFAULT '0' COMMENT '是否允许再次考试',
  `status` enum('draft','published','finished') DEFAULT 'draft',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exams`
--

LOCK TABLES `exams` WRITE;
/*!40000 ALTER TABLE `exams` DISABLE KEYS */;
INSERT INTO `exams` VALUES (2,'全国计算机等级考试（NCRE） 一级计算机基础及 WPS Office 应用','样题','exam',2,'2025-12-01 00:00:00','2026-01-01 00:00:00',20,0,'published','2025-12-07 11:42:28'),(3,'全国计算机等级考试（NCRE） 一级网络安全素质教育','样题','exam',7,'2025-12-01 00:00:00','2026-01-01 00:00:00',20,0,'published','2025-12-07 12:06:02');
/*!40000 ALTER TABLE `exams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_bank_questions`
--

DROP TABLE IF EXISTS `question_bank_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_bank_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('single_choice','multiple_choice','judge','fill_blank','essay','big_question','answer_question') NOT NULL,
  `section_label` varchar(100) DEFAULT NULL COMMENT '大题标题或序号，如一、二',
  `options` text,
  `correct_answer` text,
  `score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `order_num` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bank_id` (`bank_id`),
  CONSTRAINT `question_bank_questions_ibfk_1` FOREIGN KEY (`bank_id`) REFERENCES `question_banks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_bank_questions`
--

LOCK TABLES `question_bank_questions` WRITE;
/*!40000 ALTER TABLE `question_bank_questions` DISABLE KEYS */;
INSERT INTO `question_bank_questions` VALUES (11,1,'现在电子计算机发展的各个阶段的区分标志是','single_choice','一、单项选择题','{\"A\":\"元器件的发展水平\",\"B\":\"计算机的运算速度\",\"C\":\"软件的发展水平\",\"D\":\"操作系统的更新换代\"}','A',10.00,1,'2025-12-07 11:21:42'),(12,1,'计算机最早的应用领域是','single_choice',NULL,'{\"A\":\"辅助工程\",\"B\":\"过程控制\",\"C\":\"数据处理\",\"D\":\"数值计算\"}','D',10.00,2,'2025-12-07 11:21:42'),(13,1,'英文缩写 CAD 的中文意思是','single_choice',NULL,'{\"A\":\"计算机辅助设计\",\"B\":\"计算机辅助制造\",\"C\":\"计算机辅助教学\",\"D\":\"计算机辅助管理\"}','A',5.00,3,'2025-12-07 11:21:42'),(14,1,'计算机中所有信息的存储都采用','single_choice',NULL,'{\"A\":\"十进制\",\"B\":\"十六进制\",\"C\":\"ASCII 码\",\"D\":\"二进制\"}','D',10.00,4,'2025-12-07 11:21:42'),(15,1,'计算机病毒是指“能够侵入计算机系统并在计算机系统中潜伏、传播，破坏系统正常工作的一种具有繁殖能力的”','single_choice',NULL,'{\"A\":\"特殊程序\",\"B\":\"源程序\",\"C\":\"特殊微生物\",\"D\":\"流行性感冒病毒\"}','A',10.00,5,'2025-12-07 11:21:42'),(16,1,'多媒体处理的是','single_choice',NULL,'{\"A\":\"模拟信号\",\"B\":\"音频信号\",\"C\":\"视频信号\",\"D\":\"数字信号\"}','D',10.00,6,'2025-12-07 11:21:42'),(17,1,'浏览器是用于实现多种网络功能的软件，下列不属于浏览器的是','single_choice',NULL,'{\"A\":\"Internet Explorer\",\"B\":\"Mozilla Firefox\",\"C\":\"Google Chrome\",\"D\":\"Windows Media Player\"}','D',10.00,7,'2025-12-07 11:21:42'),(18,1,'下列叙述和计算机安全相关的是','single_choice',NULL,'{\"A\":\"设置 8 位以上开机密码并定期更换\",\"B\":\"购买正版的反病毒软件并及时更新病毒库\",\"C\":\"为所使用的计算机安装防火墙\",\"D\":\"以上选项全部都是\"}','D',10.00,8,'2025-12-07 11:21:42'),(19,1,'下列各进制的整数中，值最大的是','single_choice',NULL,'{\"A\":\"十进制数 10\",\"B\":\"八进制数 10\",\"C\":\"十六进制数 10\",\"D\":\"二进制数 10\"}','C',10.00,9,'2025-12-07 11:21:42'),(20,1,'下列不属于操作系统的是','single_choice',NULL,'{\"A\":\"Microsoft Office 2010\",\"B\":\"Linux\",\"C\":\"DOS\",\"D\":\"Windows 7\"}','A',10.00,10,'2025-12-07 11:21:42'),(21,2,'能够感染 EXE、COM 文件的病毒属于','single_choice','一、单项选择题','{\"A\":\"网络型病毒\",\"B\":\"蠕虫型病毒\",\"C\":\"文件型病毒\",\"D\":\"系统引导型病毒\"}','C',10.00,1,'2025-12-07 11:58:28'),(22,2,'计算机网络按网络范围由小到大划分为','single_choice',NULL,'{\"A\":\"局域网、城域网、广域网\",\"B\":\"城域网、局域网、广域网\",\"C\":\"广域网、城域网、局域网\",\"D\":\"局域网、广域网、城域网\"}','A',10.00,2,'2025-12-07 11:58:28'),(23,2,'关于移动终端隐私说法不正确的是','single_choice',NULL,'{\"A\":\"隐私指隐蔽、不公开的私事\",\"B\":\"隐私包含不能或不愿示人的事或物\",\"C\":\"SIM 卡上的信息不属于隐私\",\"D\":\"手机序列号是非常重要的隐私数据\"}','C',10.00,3,'2025-12-07 11:58:28'),(24,2,'下列传输媒体中传输速率最快的是','single_choice',NULL,'{\"A\":\"双绞线\",\"B\":\"光纤\",\"C\":\"蓝牙\",\"D\":\"同轴电缆\"}','B',10.00,4,'2025-12-07 11:58:28'),(25,2,'有线网络和无线网络是按照什么来分的','single_choice',NULL,'{\"A\":\"网络覆盖范围\",\"B\":\"网络用户\",\"C\":\"网络拓扑结构\",\"D\":\"网络传输介质\"}','D',10.00,5,'2025-12-07 11:58:28'),(26,2,'网络安全的根源可能存在于哪方面','single_choice','D','{\"A\":\"TCP/IP 协议的安全\",\"B\":\"操作系统本身的安全\",\"C\":\"应用程序的安全\",\"D\":\"以上都是\"}','D',10.00,6,'2025-12-07 11:58:28'),(27,2,'网络资源在需要时即可使用，不因系统故障或误操作等使资源丢失或妨碍对资源的使用\r\n指的是','single_choice',NULL,'{\"A\":\"可用性\",\"B\":\"保密性\",\"C\":\"完整性\",\"D\":\"可控性\"}','A',10.00,7,'2025-12-07 11:58:28'),(28,2,'计算机信息系统安全保护等级划分准则（GB17895-1999）将系统安全划分为几个级别','single_choice',NULL,'{\"A\":\"3\",\"B\":\"5\",\"C\":\"6\",\"D\":\".7\"}','B',10.00,8,'2025-12-07 11:58:28'),(29,2,'黑客入侵攻击的准备阶段是为了','single_choice',NULL,'{\"A\":\"确定攻击目标\",\"B\":\"收集攻击目标相关信息\",\"C\":\"准备攻击工具\",\"D\":\"以上都是\"}','D',10.00,9,'2025-12-07 11:58:28'),(30,2,'防范网络窃听最有效的方法是','single_choice',NULL,'{\"A\":\"安装防火墙\",\"B\":\"采用无线网络传输\",\"C\":\"对数据进行加密\",\"D\":\"漏洞扫描\"}','C',10.00,10,'2025-12-07 11:58:28');
/*!40000 ALTER TABLE `question_bank_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_bank_shares`
--

DROP TABLE IF EXISTS `question_bank_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_bank_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_id` int(11) NOT NULL,
  `owner_teacher_id` int(11) NOT NULL,
  `target_teacher_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bank_share` (`bank_id`,`target_teacher_id`),
  KEY `owner_teacher_id` (`owner_teacher_id`),
  KEY `target_teacher_id` (`target_teacher_id`),
  CONSTRAINT `question_bank_shares_ibfk_1` FOREIGN KEY (`bank_id`) REFERENCES `question_banks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `question_bank_shares_ibfk_2` FOREIGN KEY (`owner_teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `question_bank_shares_ibfk_3` FOREIGN KEY (`target_teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_bank_shares`
--

LOCK TABLES `question_bank_shares` WRITE;
/*!40000 ALTER TABLE `question_bank_shares` DISABLE KEYS */;
/*!40000 ALTER TABLE `question_bank_shares` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_banks`
--

DROP TABLE IF EXISTS `question_banks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_banks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL COMMENT '题库所属老师',
  `name` varchar(200) NOT NULL COMMENT '题库名称',
  `description` text COMMENT '题库说明',
  `status` enum('draft','published') NOT NULL DEFAULT 'draft' COMMENT '发布状态',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `question_banks_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_banks`
--

LOCK TABLES `question_banks` WRITE;
/*!40000 ALTER TABLE `question_banks` DISABLE KEYS */;
INSERT INTO `question_banks` VALUES (1,2,'全国计算机等级考试（NCRE） 一级计算机基础及 WPS Office 应用','样题','draft','2025-12-07 10:49:06','2025-12-07 11:28:35'),(2,7,'全国计算机等级考试（NCRE） 一级网络安全素质教育','样题','draft','2025-12-07 11:58:28','2025-12-07 11:58:28');
/*!40000 ALTER TABLE `question_banks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('single_choice','multiple_choice','judge','fill_blank','essay','big_question','answer_question') NOT NULL,
  `section_label` varchar(100) DEFAULT NULL COMMENT '大题标题或序号，如一、二',
  `options` text COMMENT 'JSON格式存储选项，适用于选择题',
  `correct_answer` text COMMENT '正确答案',
  `score` decimal(5,2) NOT NULL DEFAULT '0.00',
  `order_num` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES (11,2,'现在电子计算机发展的各个阶段的区分标志是','single_choice','一、单项选择题','{\"A\":\"元器件的发展水平\",\"B\":\"计算机的运算速度\",\"C\":\"软件的发展水平\",\"D\":\"操作系统的更新换代\"}','A',10.00,1,'2025-12-07 11:42:28'),(12,2,'计算机最早的应用领域是','single_choice',NULL,'{\"A\":\"辅助工程\",\"B\":\"过程控制\",\"C\":\"数据处理\",\"D\":\"数值计算\"}','D',10.00,2,'2025-12-07 11:42:28'),(13,2,'英文缩写 CAD 的中文意思是','single_choice',NULL,'{\"A\":\"计算机辅助设计\",\"B\":\"计算机辅助制造\",\"C\":\"计算机辅助教学\",\"D\":\"计算机辅助管理\"}','A',10.00,3,'2025-12-07 11:42:28'),(14,2,'计算机中所有信息的存储都采用','single_choice',NULL,'{\"A\":\"十进制\",\"B\":\"十六进制\",\"C\":\"ASCII 码\",\"D\":\"二进制\"}','D',10.00,4,'2025-12-07 11:42:28'),(15,2,'计算机病毒是指“能够侵入计算机系统并在计算机系统中潜伏、传播，破坏系统正常工作的一种具有繁殖能力的”','single_choice',NULL,'{\"A\":\"特殊程序\",\"B\":\"B源程序\",\"C\":\"特殊微生物\",\"D\":\"流行性感冒病毒\"}','A',10.00,5,'2025-12-07 11:42:28'),(16,2,'多媒体处理的是','single_choice',NULL,'{\"A\":\"模拟信号\",\"B\":\"音频信号\",\"C\":\"视频信号\",\"D\":\"数字信号\"}','D',10.00,6,'2025-12-07 11:42:28'),(17,2,'浏览器是用于实现多种网络功能的软件，下列不属于浏览器的是','single_choice',NULL,'{\"A\":\"Internet Explorer\",\"B\":\"Mozilla Firefox\",\"C\":\"Google Chrome\",\"D\":\"Windows Media Player\"}','D',10.00,7,'2025-12-07 11:42:28'),(18,2,'下列叙述和计算机安全相关的是','single_choice',NULL,'{\"A\":\"设置 8 位以上开机密码并定期更换\",\"B\":\"购买正版的反病毒软件并及时更新病毒库\",\"C\":\"为所使用的计算机安装防火墙\",\"D\":\"以上选项全部都是\"}','D',10.00,8,'2025-12-07 11:42:28'),(19,2,'下列各进制的整数中，值最大的是','single_choice',NULL,'{\"A\":\"十进制数 10\",\"B\":\"八进制数 10\",\"C\":\"十六进制数 10\",\"D\":\"二进制数 10\"}','C',10.00,9,'2025-12-07 11:42:28'),(20,2,'下列不属于操作系统的是','single_choice',NULL,'{\"A\":\"Microsoft Office 2010\",\"B\":\"Linux\",\"C\":\"DOS\",\"D\":\"Windows 7\"}','A',10.00,10,'2025-12-07 11:42:28'),(21,3,'能够感染 EXE、COM 文件的病毒属于','single_choice','一、单项选择题','{\"A\":\"网络型病毒\",\"B\":\"蠕虫型病毒\",\"C\":\"文件型病毒\",\"D\":\"系统引导型病毒\"}','C',10.00,1,'2025-12-07 12:06:02'),(22,3,'计算机网络按网络范围由小到大划分为','single_choice',NULL,'{\"A\":\"局域网、城域网、广域网\",\"B\":\"城域网、局域网、广域网\",\"C\":\"广域网、城域网、局域网\",\"D\":\"局域网、广域网、城域网\"}','A',10.00,2,'2025-12-07 12:06:02'),(23,3,'关于移动终端隐私说法不正确的是','single_choice',NULL,'{\"A\":\"隐私指隐蔽、不公开的私事\",\"B\":\"隐私包含不能或不愿示人的事或物\",\"C\":\"SIM 卡上的信息不属于隐私\",\"D\":\"手机序列号是非常重要的隐私数据\"}','C',10.00,3,'2025-12-07 12:06:02'),(24,3,'下列传输媒体中传输速率最快的是','single_choice',NULL,'{\"A\":\"双绞线\",\"B\":\"光纤\",\"C\":\"蓝牙\",\"D\":\"同轴电缆\"}','B',10.00,4,'2025-12-07 12:06:02'),(25,3,'有线网络和无线网络是按照什么来分的','single_choice',NULL,'{\"A\":\"网络覆盖范围\",\"B\":\"网络用户\",\"C\":\"网络拓扑结构\",\"D\":\"网络传输介质\"}','D',10.00,5,'2025-12-07 12:06:02'),(26,3,'网络安全的根源可能存在于哪方面','single_choice',NULL,'{\"A\":\"TCP/IP 协议的安全\",\"B\":\"操作系统本身的安全\",\"C\":\"应用程序的安全\",\"D\":\"以上都是\"}','D',10.00,6,'2025-12-07 12:06:02'),(27,3,'网络资源在需要时即可使用，不因系统故障或误操作等使资源丢失或妨碍对资源的使用\r\n指的是','single_choice',NULL,'{\"A\":\"可用性\",\"B\":\"保密性\",\"C\":\"完整性\",\"D\":\"可控性\"}','A',10.00,7,'2025-12-07 12:06:02'),(28,3,'计算机信息系统安全保护等级划分准则（GB17895-1999）将系统安全划分为几个级别','single_choice',NULL,'{\"A\":\"3\",\"B\":\"5\",\"C\":\"6\",\"D\":\"7\"}','B',10.00,8,'2025-12-07 12:06:02'),(29,3,'黑客入侵攻击的准备阶段是为了','single_choice',NULL,'{\"A\":\"确定攻击目标\",\"B\":\"收集攻击目标相关信息\",\"C\":\"准备攻击工具\",\"D\":\"以上都是\"}','D',10.00,9,'2025-12-07 12:06:02'),(30,3,'防范网络窃听最有效的方法是','single_choice',NULL,'{\"A\":\"安装防火墙\",\"B\":\"采用无线网络传输\",\"C\":\"对数据进行加密\",\"D\":\"漏洞扫描\"}','C',10.00,10,'2025-12-07 12:06:02');
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scores`
--

DROP TABLE IF EXISTS `scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `total_score` decimal(10,2) DEFAULT '0.00',
  `auto_score` decimal(10,2) DEFAULT '0.00' COMMENT '自动批改得分',
  `manual_score` decimal(10,2) DEFAULT '0.00' COMMENT '手动评分得分',
  `status` enum('submitted','graded','published') DEFAULT 'submitted',
  `submitted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `graded_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_score` (`exam_id`,`student_id`),
  KEY `exam_id` (`exam_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `scores_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scores`
--

LOCK TABLES `scores` WRITE;
/*!40000 ALTER TABLE `scores` DISABLE KEYS */;
/*!40000 ALTER TABLE `scores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_classes`
--

DROP TABLE IF EXISTS `teacher_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL COMMENT '老师ID',
  `class_id` int(11) NOT NULL COMMENT '班级ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_class` (`teacher_id`,`class_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `teacher_classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_classes_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_classes`
--

LOCK TABLES `teacher_classes` WRITE;
/*!40000 ALTER TABLE `teacher_classes` DISABLE KEYS */;
INSERT INTO `teacher_classes` VALUES (7,2,2,'2025-12-04 14:53:07'),(8,7,3,'2025-12-06 20:55:24');
/*!40000 ALTER TABLE `teacher_classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(50) NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `class_id` int(11) DEFAULT NULL COMMENT '班级ID，学生所属班级',
  `teacher_id` int(11) DEFAULT NULL COMMENT '创建该学生的老师ID',
  `created_by` int(11) DEFAULT NULL COMMENT '创建者ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `teacher_id` (`teacher_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$xgCjFsrgKsaqkWw9LunEIOxccNtOHD25p1s8LbNT1SZJffN6dCnFm','超级管理员','admin',NULL,NULL,NULL,'2025-12-03 17:15:17'),(2,'teacher1','$2y$10$xgCjFsrgKsaqkWw9LunEIOxccNtOHD25p1s8LbNT1SZJffN6dCnFm','张老师','teacher',2,NULL,NULL,'2025-12-03 17:15:17'),(3,'student1','$2y$10$xgCjFsrgKsaqkWw9LunEIOxccNtOHD25p1s8LbNT1SZJffN6dCnFm','林雅南','student',2,NULL,NULL,'2025-12-03 17:15:17'),(7,'teacher2','$2y$10$9MKvBtrFC97h2lu1LhIX2effDFrTHIP9cwtEWJBvuXwjKCRp4Rjnu','袁老师','teacher',3,NULL,NULL,'2025-12-04 15:30:13'),(8,'student2','$2y$10$VFsAWBrd/qZcadpUMaZfL.jSPj5bcZCilM7dPa4ctu1fZECvtjDS.','雯静','student',3,NULL,NULL,'2025-12-04 15:31:17'),(14,'student','$2y$10$24XbmAatmv9/g5UMAHpkW.syVbJ77QvVAHqnG8MS5cCMB1uNPd8Je','默认学生','student',NULL,NULL,NULL,'2025-12-07 15:03:57');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- 删除默认学生账号和老师账号
-- 执行此脚本将删除数据库中用户名='student'和username='teacher'的账号
--

-- 删除默认学生账号（用户名='student'）
DELETE FROM users WHERE username = 'student' AND role = 'student';

-- 删除默认老师账号（用户名='teacher'）
DELETE FROM users WHERE username = 'teacher' AND role = 'teacher';

--
-- Dumping events for database 'admin'
--

--
-- Dumping routines for database 'admin'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-07 15:04:34
