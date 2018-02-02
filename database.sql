--
-- Table structure for table `_piropazo_crowns`
--

CREATE TABLE `_piropazo_crowns` (
  `id` int(11) NOT NULL,
  `email` char(100) NOT NULL,
  `crowned` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_piropazo_flowers`
--

CREATE TABLE `_piropazo_flowers` (
  `id` int(11) NOT NULL,
  `sender` char(100) NOT NULL,
  `receiver` char(100) NOT NULL,
  `message` varchar(300) DEFAULT NULL,
  `sent` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_piropazo_people`
--

CREATE TABLE `_piropazo_people` (
  `email` char(100) NOT NULL,
  `flowers` int(5) NOT NULL DEFAULT '10',
  `crowns` int(5) NOT NULL DEFAULT '2',
  `likes` int(11) NOT NULL DEFAULT '0',
  `dislikes` int(11) NOT NULL DEFAULT '0',
  `crowned` timestamp NULL DEFAULT NULL COMMENT 'Last time the user was king/queen',
  `first_access` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_access` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_piropazo_relationships`
--

CREATE TABLE `_piropazo_relationships` (
  `id` int(11) NOT NULL,
  `email_from` char(100) NOT NULL,
  `email_to` char(100) NOT NULL,
  `status` enum('like','dislike','match','blocked') NOT NULL,
  `expires_matched_blocked` timestamp NULL DEFAULT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_piropazo_reports`
--

CREATE TABLE `_piropazo_reports` (
  `id` int(11) NOT NULL,
  `creator` char(100) NOT NULL COMMENT 'The one who reports',
  `user` char(100) NOT NULL COMMENT 'Person reported',
  `type` enum('OFFENSIVE','FAKE','MISLEADING','IMPERSONATING','COPYRIGHT') NOT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `_piropazo_crowns`
--
ALTER TABLE `_piropazo_crowns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `_piropazo_flowers`
--
ALTER TABLE `_piropazo_flowers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `_piropazo_people`
--
ALTER TABLE `_piropazo_people`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `_piropazo_relationships`
--
ALTER TABLE `_piropazo_relationships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `email_from` (`email_from`),
  ADD KEY `email_to` (`email_to`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `_piropazo_reports`
--
ALTER TABLE `_piropazo_reports`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `_piropazo_crowns`
--
ALTER TABLE `_piropazo_crowns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3969;
--
-- AUTO_INCREMENT for table `_piropazo_flowers`
--
ALTER TABLE `_piropazo_flowers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9189;
--
-- AUTO_INCREMENT for table `_piropazo_relationships`
--
ALTER TABLE `_piropazo_relationships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=418656;
--
-- AUTO_INCREMENT for table `_piropazo_reports`
--
ALTER TABLE `_piropazo_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;