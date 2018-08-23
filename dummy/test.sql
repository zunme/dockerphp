-- --------------------------------------------------------
-- 호스트:                          172.30.1.25
-- 서버 버전:                        10.2.14-MariaDB-10.2.14+maria~jessie - mariadb.org binary distribution
-- 서버 OS:                        debian-linux-gnu
-- HeidiSQL 버전:                  9.5.0.5196
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- 테이블 database.zn_member 구조 내보내기
DROP TABLE IF EXISTS `zn_member`;
CREATE TABLE IF NOT EXISTS `zn_member` (
  `mem_idx` int(11) NOT NULL AUTO_INCREMENT,
  `mem_key` varchar(50) NOT NULL COMMENT 'Member Uniq key for seyfert',
  `mem_id` varchar(50) NOT NULL,
  `mem_name` varchar(40) NOT NULL,
  `mem_pw` varchar(100) NOT NULL,
  `is_mem` enum('Y','N') NOT NULL DEFAULT 'Y' COMMENT '회원여부 (N:탈퇴)',
  `mem_gender` enum('W','M') NOT NULL DEFAULT 'M' COMMENT '성별',
  `member_email` varchar(50) NOT NULL DEFAULT '',
  `member_phone` varchar(20) NOT NULL DEFAULT '',
  `member_corp_type` enum('P','C') NOT NULL DEFAULT 'P' COMMENT 'P:Private, C:Corp',
  `mem_is_borrower` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT '차입자여부',
  `member_type` smallint(6) NOT NULL DEFAULT 1 COMMENT 'zu_member_type.typeno',
  `reqMemGuid` varchar(50) NOT NULL DEFAULT '' COMMENT '세이퍼트키',
  `mem_birth` date NOT NULL,
  `virtuak_acc_bnk` varchar(10) NOT NULL DEFAULT '' COMMENT '가상계좌',
  `virtual_acc_no` varchar(30) NOT NULL DEFAULT '',
  `virtual_acc_name` varchar(40) NOT NULL DEFAULT '',
  `real_acc_status` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT '실계좌 인증여부',
  `real_acc_bnk` varchar(10) NOT NULL DEFAULT '',
  `real_acc_no` varchar(30) NOT NULL DEFAULT '',
  `real_acc_name` varchar(40) NOT NULL DEFAULT '',
  `auth_method` enum('N','I','H') NOT NULL DEFAULT 'N' COMMENT 'I:아이핀, H:휴대폰 인증',
  `auth_key` varchar(100) NOT NULL DEFAULT '' COMMENT '인증 동일회원구분 키',
  `reg_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `leave_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`mem_idx`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- 테이블 데이터 database.zn_member:~0 rows (대략적) 내보내기
/*!40000 ALTER TABLE `zn_member` DISABLE KEYS */;
INSERT INTO `zn_member` (`mem_idx`, `mem_key`, `mem_id`, `mem_name`, `mem_pw`, `is_mem`, `mem_gender`, `member_email`, `member_phone`, `member_corp_type`, `mem_is_borrower`, `member_type`, `reqMemGuid`, `mem_birth`, `virtuak_acc_bnk`, `virtual_acc_no`, `virtual_acc_name`, `real_acc_status`, `real_acc_bnk`, `real_acc_no`, `real_acc_name`, `auth_method`, `auth_key`, `reg_date`, `leave_date`) VALUES
	(1, '23a8a85e28e136710e92a1f178ef6f92', 'zunme@nate.com', '임성택', '12312313', 'Y', 'M', 'zunme@nate.com', '01025376460', 'P', 'N', 1, '6Dt3M8fhy4626dWwykrwD9', '0000-00-00', '', '', '', 'N', '', '', '', 'N', '', '2018-08-16 04:49:23', '0000-00-00 00:00:00');
/*!40000 ALTER TABLE `zn_member` ENABLE KEYS */;

-- 테이블 database.zu_member_balance 구조 내보내기
DROP TABLE IF EXISTS `zu_member_balance`;
CREATE TABLE IF NOT EXISTS `zu_member_balance` (
  `fk_zu_member_mem_idx` int(11) NOT NULL,
  `mem_balance` int(10) unsigned NOT NULL DEFAULT 0,
  `mem_point` int(10) unsigned NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='회원 잔액 테이블';

-- 테이블 데이터 database.zu_member_balance:~0 rows (대략적) 내보내기
/*!40000 ALTER TABLE `zu_member_balance` DISABLE KEYS */;
INSERT INTO `zu_member_balance` (`fk_zu_member_mem_idx`, `mem_balance`, `mem_point`) VALUES
	(1, 0, 0);
/*!40000 ALTER TABLE `zu_member_balance` ENABLE KEYS */;

-- 테이블 database.zu_member_type 구조 내보내기
DROP TABLE IF EXISTS `zu_member_type`;
CREATE TABLE IF NOT EXISTS `zu_member_type` (
  `m_type_idx` int(11) NOT NULL AUTO_INCREMENT,
  `m_type_label` varchar(40) NOT NULL,
  `m_type_useauth` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT '승인후 투자가능여부',
  `m_type_corp` enum('P','C') NOT NULL DEFAULT 'P',
  `m_type_per_max` int(11) NOT NULL COMMENT '동일차주 최대한도',
  `m_type_limit_realestate` int(11) NOT NULL COMMENT '부동산',
  `m_type_limit_movables` int(11) NOT NULL COMMENT '동산',
  `m_withholding1` varchar(10) NOT NULL DEFAULT '0.25',
  `m_withholding` varchar(10) NOT NULL DEFAULT '0.025',
  PRIMARY KEY (`m_type_idx`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- 테이블 데이터 database.zu_member_type:~1 rows (대략적) 내보내기
/*!40000 ALTER TABLE `zu_member_type` DISABLE KEYS */;
INSERT INTO `zu_member_type` (`m_type_idx`, `m_type_label`, `m_type_useauth`, `m_type_corp`, `m_type_per_max`, `m_type_limit_realestate`, `m_type_limit_movables`, `m_withholding1`, `m_withholding`) VALUES
	(1, '일반투자자', 'N', 'P', 5000000, 10000000, 20000000, '0.25', '0.025');
/*!40000 ALTER TABLE `zu_member_type` ENABLE KEYS */;

-- 트리거 database.zn_member_after_insert 구조 내보내기
DROP TRIGGER IF EXISTS `zn_member_after_insert`;
SET @OLDTMP_SQL_MODE=@@SQL_MODE, SQL_MODE='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';
DELIMITER //
CREATE TRIGGER `zn_member_after_insert` AFTER INSERT ON `zn_member` FOR EACH ROW BEGIN
 INSERT INTO zu_member_balance (`fk_zu_member_mem_idx`, `mem_balance`) values(NEW.mem_idx, 0);
END//
DELIMITER ;
SET SQL_MODE=@OLDTMP_SQL_MODE;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
