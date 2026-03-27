<?php

namespace App;

const UA_DATE_FORMAT = 'd.m.Y';
const TABLE_DATE_FORMAT = 'd.m';
const LOCAL_TIMEZONE = 'Europe/Kiev';

class Row {
    public $isManualMode;
    public $date;
    public $book;
    public $verse;
    public $username;
    public $theme;
    public $time;       // Time in format "H:i" (e.g., "07:55")
    public $duration;   // Duration in minutes (e.g., "60")
//    public $title;

    public function __construct($isManualMode = false, $date = null, $dayOfWeek = null, $book = null, $verse = null, $username = null, $theme = null, $time = null, $duration = null)
    {
        $this->isManualMode = (bool)$isManualMode;
        $this->date = $date ? \DateTime::createFromFormat(UA_DATE_FORMAT, $date, new \DateTimeZone(LOCAL_TIMEZONE)) : null;
        $this->book = $book;
        $this->verse = $verse;
        $this->theme = $theme;
        $this->username = $username;
        $this->time = $time;
        $this->duration = $duration ? (int)$duration : null;
    }

    public function isValid()
    {
        return $this->book && $this->verse && $this->username;
    }

    function isToday(): bool
    {
        // Use LOCAL_TIMEZONE for date comparison
        $now = new \DateTime('now', new \DateTimeZone(LOCAL_TIMEZONE));
        return $this->date instanceof \DateTime && $this->date->format(UA_DATE_FORMAT) == $now->format(UA_DATE_FORMAT);
    }

    function isWeekTransition($prevRow): bool
    {
        return $prevRow->date instanceof \DateTime && $this->date instanceof \DateTime
            && $this->date->format('N') < $prevRow->date->format('N');
    }

    function dateFormatted()
    {
        return $this->date instanceof \DateTime ? $this->date->format(UA_DATE_FORMAT) : '';
    }

    function dateTableFormat()
    {
        return $this->date instanceof \DateTime
            ? Utils::getLocalTimeStr($this->date, 'EEEEEE, d MMM')
            : '';
    }

    function dayOfWeek()
    {
        if ($this->date instanceof \DateTime) {
            return Utils::getLocalTimeStr($this->date, 'EEEEEE');
        }
        return '';
    }

    /**
     * Check if the scheduled time matches current time (within 5 minute tolerance)
     */
    function isScheduledNow(): bool
    {
        if (!$this->time || !$this->date instanceof \DateTime) {
            return false;
        }
        
        // Check if today is the scheduled date
        if (!$this->isToday()) {
            return false;
        }
        
        // Parse scheduled time in LOCAL_TIMEZONE
        $scheduledTime = \DateTime::createFromFormat('H:i', $this->time, new \DateTimeZone(LOCAL_TIMEZONE));
        if (!$scheduledTime) {
            return false;
        }
        
        // Get current time in LOCAL_TIMEZONE
        $now = new \DateTime('now', new \DateTimeZone(LOCAL_TIMEZONE));
        
        // Compare hours and minutes
        $scheduledMinutes = (int)$scheduledTime->format('H') * 60 + (int)$scheduledTime->format('i');
        $nowMinutes = (int)$now->format('H') * 60 + (int)$now->format('i');
        
        // Allow 5 minute tolerance for cron job running slightly late
        // This ensures broadcasts still start if cron is delayed
        return abs($scheduledMinutes - $nowMinutes) <= 5;
    }

    /**
     * Get broadcast end time based on scheduled time + duration
     */
    function getScheduledEndTime(): ?\DateTime
    {
        if (!$this->time || !$this->duration) {
            return null;
        }
        
        $startTime = \DateTime::createFromFormat('H:i', $this->time, new \DateTimeZone(LOCAL_TIMEZONE));
        if (!$startTime) {
            return null;
        }
        
        // Create a DateTime for today at the scheduled time in LOCAL_TIMEZONE
        $endTime = new \DateTime('now', new \DateTimeZone(LOCAL_TIMEZONE));
        $endTime->setTime((int)$startTime->format('H'), (int)$startTime->format('i'));
        $endTime->modify("+{$this->duration} minutes");
        
        return $endTime;
    }

    /**
     * Check if the broadcast should stop now based on scheduled end time
     */
    function shouldStopNow(): bool
    {
        if (!$this->duration || !$this->date instanceof \DateTime) {
            return false;
        }
        
        if (!$this->isToday()) {
            return false;
        }
        
        $endTime = $this->getScheduledEndTime();
        if (!$endTime) {
            return false;
        }
        
        // Get current time in LOCAL_TIMEZONE
        $now = new \DateTime('now', new \DateTimeZone(LOCAL_TIMEZONE));
        
        // Check if current time is at or past the end time (with 1 minute tolerance)
        return $now >= $endTime || ($now->format('H:i') === $endTime->format('H:i'));
    }
}