--
-- Table structure for table `_piropazo_cache`
--

CREATE TABLE `_piropazo_cache` (
  `id` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `suggestion` int(11) NOT NULL,
  `match` int(5) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Cache the list of users suggested to a person';

--
-- Table structure for table `_piropazo_flowers`
--

CREATE TABLE `_piropazo_flowers` (
  `id` int(11) NOT NULL,
  `id_sender` int(11) NOT NULL,
  `id_receiver` int(11) NOT NULL,
  `message` varchar(300) DEFAULT NULL,
  `sent` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `_piropazo_people`
--

CREATE TABLE `_piropazo_people` (
  `id_person` int(11) NOT NULL,
  `flowers` int(5) NOT NULL DEFAULT '10',
  `crowns` int(5) NOT NULL DEFAULT '2',
  `likes` int(11) NOT NULL DEFAULT '0',
  `dislikes` int(11) NOT NULL DEFAULT '0',
  `crowned` timestamp NULL DEFAULT NULL COMMENT 'Last time the user was king/queen',
  `first_access` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_access` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `_piropazo_relationships`
--

CREATE TABLE `_piropazo_relationships` (
  `id` int(11) NOT NULL,
  `id_from` int(11) NOT NULL,
  `id_to` int(11) NOT NULL,
  `status` enum('like','dislike','match','blocked') NOT NULL,
  `expires_matched_blocked` timestamp NULL DEFAULT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `_piropazo_reports`
--

CREATE TABLE `_piropazo_reports` (
  `id` int(11) NOT NULL,
  `id_reporter` int(11) NOT NULL,
  `id_violator` int(11) NOT NULL,
  `type` enum('OFFENSIVE','FAKE','MISLEADING','IMPERSONATING','COPYRIGHT') NOT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `_piropazo_cache`
--
ALTER TABLE `_piropazo_cache`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `_piropazo_flowers`
--
ALTER TABLE `_piropazo_flowers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_sender` (`id_sender`),
  ADD KEY `id_receiver` (`id_receiver`);

--
-- Indexes for table `_piropazo_people`
--
ALTER TABLE `_piropazo_people`
  ADD PRIMARY KEY (`id_person`);

--
-- Indexes for table `_piropazo_relationships`
--
ALTER TABLE `_piropazo_relationships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_from` (`id_from`),
  ADD KEY `id_to` (`id_to`),
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
-- AUTO_INCREMENT for table `_piropazo_cache`
--
ALTER TABLE `_piropazo_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `_piropazo_relationships`
--
ALTER TABLE `_piropazo_relationships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `_piropazo_reports`
--
ALTER TABLE `_piropazo_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT;