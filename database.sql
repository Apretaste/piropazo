--
-- Table structure for table `_piropazo_crowns`
--

CREATE TABLE IF NOT EXISTS `_piropazo_crowns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` char(100) NOT NULL,
  `crowned` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Table structure for table `_piropazo_flowers`
--

CREATE TABLE IF NOT EXISTS `_piropazo_flowers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender` char(100) NOT NULL,
  `receiver` char(100) NOT NULL,
  `sent` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=10 ;

-- --------------------------------------------------------

--
-- Table structure for table `_piropazo_people`
--

CREATE TABLE IF NOT EXISTS `_piropazo_people` (
  `email` char(100) NOT NULL,
  `flowers` int(5) NOT NULL DEFAULT '3',
  `crowns` int(5) NOT NULL DEFAULT '1',
  `likes` int(11) NOT NULL DEFAULT '0',
  `dislikes` int(11) NOT NULL DEFAULT '0',
  `crowned` timestamp NULL DEFAULT NULL COMMENT 'Last time the user was king/queen',
  `first_access` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_access` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_piropazo_relationships`
--

CREATE TABLE IF NOT EXISTS `_piropazo_relationships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email_from` char(100) NOT NULL,
  `email_to` char(100) NOT NULL,
  `status` enum('like','dislike','match','blocked') NOT NULL,
  `expires_matched_blocked` timestamp NULL DEFAULT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=13 ;
