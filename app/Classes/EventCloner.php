<?php

namespace App\Classes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventCloner
{
    protected $idMap = [
        'event' => [],
        'project' => [],
        'directory' => [],
        'directoryentry' => [],
        'hunt' => [],
        'huntitem' => [],
        'news' => [],
        'newsitem' => [],
        'poll' => [],
        'pollitem' => [],
        'shop' => [],
        'shopitem' => [],
        'pindrop' => [],
        'schedule' => [],
        // Add more as needed
    ];

    // Accumulate mappings for second-pass fixups
    protected $directoryOldToNew = [];
    protected $directoryEntryOldToNew = [];

    public function cloneEventForUser($demoEventId, $userId)
    {
        Log::info("Starting event clone: source event_id=$demoEventId, target user_id=$userId");

        $newEventId = DB::transaction(function () use ($demoEventId, $userId) {
            $newEventId = $this->cloneEvent($demoEventId, $userId);

            $this->cloneProjects($demoEventId, $newEventId);

            // Fix up parent ids for ALL directories and entries after all have been inserted
            $this->fixDirectoryParentIds();
            $this->fixDirectoryMapIds(); 
            $this->fixDirectoryEntryParentIds();
            $this->fixNewsItemDirectoryEntryIds();

            $this->cloneSchedules($demoEventId, $newEventId);
            $this->cloneGuests($demoEventId, $newEventId);
            $this->cloneInstalls($demoEventId, $newEventId);
            $this->cloneLookups($demoEventId, $newEventId);

            Log::info("Event clone complete: new_event_id=$newEventId for user_id=$userId");
            return $newEventId;
        });

        return $newEventId;
    }

    protected function cloneEvent($demoEventId, $userId)
    {
        $event = DB::table('event')->where('event_id', $demoEventId)->first();
        $newEvent = (array) $event;
        unset($newEvent['event_id']);

        $newEvent['event_name'] = $event->event_name . ' Demo';
        $newEvent['event_userid'] = $userId;

        $newEventId = DB::table('event')->insertGetId($newEvent);

        $this->idMap['event'][$demoEventId] = $newEventId;

        Log::info("Cloned event: $demoEventId -> $newEventId (user_id=$userId, name=\"{$newEvent['event_name']}\")");
        return $newEventId;
    }

    protected function cloneProjects($oldEventId, $newEventId)
    {
        $projects = DB::table('project')->where('project_eventid', $oldEventId)->get();
        Log::info("Cloning " . count($projects) . " projects for event $oldEventId");
        foreach ($projects as $project) {
            $oldProjectId = $project->project_id;
            $newProject = (array) $project;
            unset($newProject['project_id']);
            $newProject['project_eventid'] = $newEventId;

            $newProjectId = DB::table('project')->insertGetId($newProject);
            $this->idMap['project'][$oldProjectId] = $newProjectId;

            Log::info("Cloned project: $oldProjectId -> $newProjectId (event_id=$newEventId)");

            $this->clonePindrops($oldProjectId, $newProjectId, $newEventId); // Each project gets its own pindrops
            $this->cloneDirectories($oldProjectId, $newProjectId, $newEventId);
            $this->cloneHunts($oldProjectId, $newProjectId, $newEventId);
            $this->cloneNews($oldProjectId, $newProjectId, $newEventId);
            $this->clonePolls($oldProjectId, $newProjectId, $newEventId);
            $this->cloneShops($oldProjectId, $newProjectId, $newEventId);
        }
    }

    /**
     * Clones pindrops for a given project, remapping pindrop_projectid as needed.
     */
    protected function clonePindrops($oldProjectId, $newProjectId, $newEventId)
    {
        $pindrops = DB::table('pindrop')->where('pindrop_projectid', $oldProjectId)->get();
        Log::info("Cloning " . count($pindrops) . " pindrops for project $oldProjectId");
        foreach ($pindrops as $pindrop) {
            $oldPindropId = $pindrop->pindrop_id;
            $newPindrop = (array) $pindrop;
            unset($newPindrop['pindrop_id']);
            $newPindrop['pindrop_eventid'] = $newEventId;
            $newPindrop['pindrop_projectid'] = $newProjectId;

            $newPindropId = DB::table('pindrop')->insertGetId($newPindrop);
            $this->idMap['pindrop'][$oldPindropId] = $newPindropId;

            Log::info("Cloned pindrop: $oldPindropId -> $newPindropId (project_id=$newProjectId, event_id=$newEventId)");
        }
    }

    /**
     * Clones directories and accumulates old=>new mapping.
     */
    protected function cloneDirectories($oldProjectId, $newProjectId, $newEventId)
    {
        $directories = DB::table('directory')->where('directory_projectid', $oldProjectId)->get();
        Log::info("Cloning " . count($directories) . " directories for project $oldProjectId");

        foreach ($directories as $directory) {
            $oldDirectoryId = $directory->directory_id;
            $newDirectory = (array) $directory;
            unset($newDirectory['directory_id']);
            $newDirectory['directory_projectid'] = $newProjectId;
            $newDirectory['directory_eventid'] = $newEventId;

            // Remap directory_mapid if present, but use old ID for now (fix in second pass)
            // (no-op: will fix in fixDirectoryMapIds)
            
            $newDirectoryId = DB::table('directory')->insertGetId($newDirectory);
            $this->directoryOldToNew[$oldDirectoryId] = $newDirectoryId;
            $this->idMap['directory'][$oldDirectoryId] = $newDirectoryId;

            $this->cloneDirectoryEntries($oldDirectoryId, $newDirectoryId, $newEventId);
        }
    }

    /**
     * Second pass: Fix up all directory parent IDs.
     */
    protected function fixDirectoryParentIds()
    {
        Log::info('--- Second pass: Directory parent ID fixup STARTING ---');
        Log::info('Directory ID Mapping JSON: ' . json_encode($this->directoryOldToNew));
        foreach ($this->directoryOldToNew as $oldDirectoryId => $newDirectoryId) {
            $directory = DB::table('directory')->where('directory_id', $newDirectoryId)->first();
            $oldParentId = $directory->directory_parentid;

            if (isset($this->directoryOldToNew[$oldParentId])) {
                $newParentId = $this->directoryOldToNew[$oldParentId];
                Log::info("Updating directory_id $newDirectoryId: setting parentid from old $oldParentId to new $newParentId");
                DB::table('directory')->where('directory_id', $newDirectoryId)
                    ->update(['directory_parentid' => $newParentId]);
            } else {
                Log::info("Setting directory_id $newDirectoryId as root (parentid=0) [oldParentId was $oldParentId]");
                DB::table('directory')->where('directory_id', $newDirectoryId)
                    ->update(['directory_parentid' => 0]);
            }
        }
        Log::info('--- Second pass: Directory parent ID fixup ENDING ---');
    }

    /**
     * Second pass: Fix up all directory map IDs using the pindrop mapping.
     */
    protected function fixDirectoryMapIds()
    {
        Log::info('--- Second pass: Directory mapid fixup STARTING ---');
        // Log the full pindrop ID map for context
        if (!empty($this->idMap['pindrop'])) {
            Log::info('Pindrop ID Mapping (old_id => new_id):');
            foreach ($this->idMap['pindrop'] as $oldPindropId => $newPindropId) {
                Log::info("   $oldPindropId => $newPindropId");
            }
        } else {
            Log::info('No pindrop ID mappings found in idMap[\'pindrop\']');
        }

        foreach ($this->directoryOldToNew as $oldDirectoryId => $newDirectoryId) {
            $directory = DB::table('directory')->where('directory_id', $newDirectoryId)->first();
            $oldMapId = $directory->directory_mapid;
            if (!empty($oldMapId)) {
                if (isset($this->idMap['pindrop'][$oldMapId])) {
                    $newMapId = $this->idMap['pindrop'][$oldMapId];
                    Log::info("Updating directory_id $newDirectoryId: setting mapid from old $oldMapId to new $newMapId");
                    DB::table('directory')->where('directory_id', $newDirectoryId)
                        ->update(['directory_mapid' => $newMapId]);
                } else {
                    Log::info("directory_id $newDirectoryId: old mapid $oldMapId has no mapping in idMap['pindrop']");
                    // Optionally set to 0/null:
                    // DB::table('directory')->where('directory_id', $newDirectoryId)
                    //     ->update(['directory_mapid' => 0]);
                }
            }
        }
        Log::info('--- Second pass: Directory mapid fixup ENDING ---');
    }

    /**
     * Clones directory entries and accumulates old=>new mapping for parent fixup.
     */
    protected function cloneDirectoryEntries($oldDirectoryId, $newDirectoryId, $newEventId)
    {
        $entries = DB::table('directoryentry')->where('directoryentry_directoryid', $oldDirectoryId)->get();
        Log::info("Cloning " . count($entries) . " directoryentries for directory $oldDirectoryId");

        foreach ($entries as $entry) {
            $oldEntryId = $entry->directoryentry_id;
            $newEntry = (array) $entry;
            unset($newEntry['directoryentry_id']);
            $newEntry['directoryentry_directoryid'] = $newDirectoryId;
            $newEntry['directoryentry_eventid'] = $newEventId;

            $newEntryId = DB::table('directoryentry')->insertGetId($newEntry);

            $this->idMap['directoryentry'][$oldEntryId] = $newEntryId;
            $this->directoryEntryOldToNew[$oldEntryId] = $newEntryId;

            Log::info("Cloned directoryentry: $oldEntryId -> $newEntryId (directory_id=$newDirectoryId)");
        }

        Log::info("directoryEntryOldToNew size after cloning directory $oldDirectoryId: " . count($this->directoryEntryOldToNew));
    }



    /**
     * Second pass: Fix up all directory entry parententryids.
     */
    protected function fixDirectoryEntryParentIds()
    {
        Log::info('--- Second pass: DirectoryEntry parententryid fixup STARTING ---');
        Log::info('DirectoryEntry ID Mapping JSON: ' . json_encode($this->directoryEntryOldToNew));
        foreach ($this->directoryEntryOldToNew as $oldEntryId => $newEntryId) {
            $entry = DB::table('directoryentry')->where('directoryentry_id', $newEntryId)->first();
            $oldParentEntryId = $entry->directoryentry_parententryid;

            if (isset($this->directoryEntryOldToNew[$oldParentEntryId])) {
                $newParentEntryId = $this->directoryEntryOldToNew[$oldParentEntryId];
                Log::info("Updating directoryentry_id $newEntryId: setting parententryid from old $oldParentEntryId to new $newParentEntryId");
                DB::table('directoryentry')->where('directoryentry_id', $newEntryId)
                    ->update(['directoryentry_parententryid' => $newParentEntryId]);
            } else {
                Log::info("Setting directoryentry_id $newEntryId as root (parententryid=0) [oldParentEntryId was $oldParentEntryId]");
                DB::table('directoryentry')->where('directoryentry_id', $newEntryId)
                    ->update(['directoryentry_parententryid' => 0]);
            }
        }
        Log::info('--- Second pass: DirectoryEntry parententryid fixup ENDING ---');
    }

    // --- Below here: the rest of your clones (unchanged, but with logging) ---

    protected function cloneSchedules($oldEventId, $newEventId)
    {
        $schedules = DB::table('schedule')->where('schedule_eventid', $oldEventId)->get();
        Log::info("Cloning " . count($schedules) . " schedules for event $oldEventId");
        foreach ($schedules as $schedule) {
            $oldScheduleId = $schedule->schedule_id;
            $newSchedule = (array) $schedule;
            unset($newSchedule['schedule_id']);
            $newSchedule['schedule_eventid'] = $newEventId;

            // Remap schedule_projectid if present
            if (!empty($newSchedule['schedule_projectid']) && isset($this->idMap['project'][$newSchedule['schedule_projectid']])) {
                $newSchedule['schedule_projectid'] = $this->idMap['project'][$newSchedule['schedule_projectid']];
            }

            $newScheduleId = DB::table('schedule')->insertGetId($newSchedule);
            $this->idMap['schedule'][$oldScheduleId] = $newScheduleId;
        }
    }

    protected function cloneHunts($oldProjectId, $newProjectId, $newEventId)
    {
        $hunts = DB::table('hunt')->where('hunt_projectid', $oldProjectId)->get();
        Log::info("Cloning " . count($hunts) . " hunts for project $oldProjectId");
        foreach ($hunts as $hunt) {
            $oldHuntId = $hunt->hunt_id;
            $newHunt = (array) $hunt;
            unset($newHunt['hunt_id']);
            $newHunt['hunt_projectid'] = $newProjectId;
            $newHunt['hunt_eventid'] = $newEventId;

            $newHuntId = DB::table('hunt')->insertGetId($newHunt);
            $this->idMap['hunt'][$oldHuntId] = $newHuntId;

            Log::info("Cloned hunt: $oldHuntId -> $newHuntId (project_id=$newProjectId)");
            $this->cloneHuntItems($oldHuntId, $newHuntId, $newEventId);
        }
    }

    protected function cloneHuntItems($oldHuntId, $newHuntId, $newEventId)
    {
        $items = DB::table('huntitem')->where('huntitem_huntid', $oldHuntId)->get();
        Log::info("Cloning " . count($items) . " huntitems for hunt $oldHuntId");
        foreach ($items as $item) {
            $newItem = (array) $item;
            unset($newItem['huntitem_id']);
            $newItem['huntitem_huntid'] = $newHuntId;
            $newItem['huntitem_eventid'] = $newEventId;
            DB::table('huntitem')->insert($newItem);
        }
    }

    protected function cloneNews($oldProjectId, $newProjectId, $newEventId)
    {
        $news = DB::table('news')->where('news_projectid', $oldProjectId)->get();
        Log::info("Cloning " . count($news) . " news for project $oldProjectId");
        foreach ($news as $newsRow) {
            $oldNewsId = $newsRow->news_id;
            $newNews = (array) $newsRow;
            unset($newNews['news_id']);
            $newNews['news_projectid'] = $newProjectId;
            $newNews['news_eventid'] = $newEventId;

            $newNewsId = DB::table('news')->insertGetId($newNews);
            $this->idMap['news'][$oldNewsId] = $newNewsId;

            Log::info("Cloned news: $oldNewsId -> $newNewsId (project_id=$newProjectId)");
            $this->cloneNewsItems($oldNewsId, $newNewsId, $newEventId, $newProjectId);
        }
    }

protected function cloneNewsItems($oldNewsId, $newNewsId, $newEventId, $newProjectId)
{
    $items = DB::table('newsitem')->where('newsitem_newsid', $oldNewsId)->get();
    Log::info("Cloning " . count($items) . " newsitems for news $oldNewsId");
    foreach ($items as $item) {
        $oldNewsItemId = $item->newsitem_id;
        $newItem = (array) $item;
        unset($newItem['newsitem_id']);
        $newItem['newsitem_newsid'] = $newNewsId;
        $newItem['newsitem_eventid'] = $newEventId;
        $newItem['newsitem_projectid'] = $newProjectId;

        $newNewsItemId = DB::table('newsitem')->insertGetId($newItem);
        $this->newsItemOldToNew[$oldNewsItemId] = $newNewsItemId;

        // Add detailed logging per newsitem
        Log::info("Cloned newsitem: $oldNewsItemId -> $newNewsItemId (news_id=$newNewsId, event_id=$newEventId, project_id=$newProjectId)");
    }
}



protected function fixNewsItemDirectoryEntryIds()
{
    Log::info('--- Second pass: NewsItem directoryentryid fixup STARTING ---');
    foreach ($this->newsItemOldToNew as $oldNewsItemId => $newNewsItemId) {
        $newsitem = DB::table('newsitem')->where('newsitem_id', $newNewsItemId)->first();
        $oldEntryId = $newsitem->newsitem_directoryentryid;

        if (!empty($oldEntryId) && isset($this->directoryEntryOldToNew[$oldEntryId])) {
            $newEntryId = $this->directoryEntryOldToNew[$oldEntryId];
            Log::info("Updating newsitem_id $newNewsItemId: setting directoryentryid from old $oldEntryId to new $newEntryId");
            DB::table('newsitem')->where('newsitem_id', $newNewsItemId)
                ->update(['newsitem_directoryentryid' => $newEntryId]);
        } else {
            Log::info("newsitem_id $newNewsItemId: old directoryentryid $oldEntryId has no mapping, setting to 0");
            DB::table('newsitem')->where('newsitem_id', $newNewsItemId)
                ->update(['newsitem_directoryentryid' => 0]);
        }
    }
    Log::info('--- Second pass: NewsItem directoryentryid fixup ENDING ---');
}


    protected function clonePolls($oldProjectId, $newProjectId, $newEventId)
    {
        $polls = DB::table('poll')->where('poll_projectid', $oldProjectId)->get();
        Log::info("Cloning " . count($polls) . " polls for project $oldProjectId");
        foreach ($polls as $poll) {
            $oldPollId = $poll->poll_id;
            $newPoll = (array) $poll;
            unset($newPoll['poll_id']);
            $newPoll['poll_projectid'] = $newProjectId;
            $newPoll['poll_eventid'] = $newEventId;

            $newPollId = DB::table('poll')->insertGetId($newPoll);
            $this->idMap['poll'][$oldPollId] = $newPollId;

            Log::info("Cloned poll: $oldPollId -> $newPollId (project_id=$newProjectId)");
            $this->clonePollItems($oldPollId, $newPollId, $newEventId);
        }
    }

    protected function clonePollItems($oldPollId, $newPollId, $newEventId)
    {
        $items = DB::table('pollitem')->where('pollitem_pollid', $oldPollId)->get();
        Log::info("Cloning " . count($items) . " pollitems for poll $oldPollId");
        foreach ($items as $item) {
            $newItem = (array) $item;
            unset($newItem['pollitem_id']);
            $newItem['pollitem_pollid'] = $newPollId;
            $newItem['pollitem_eventid'] = $newEventId;
            DB::table('pollitem')->insert($newItem);
        }
    }

    protected function cloneShops($oldProjectId, $newProjectId, $newEventId)
    {
        $shops = DB::table('shop')->where('shop_projectid', $oldProjectId)->get();
        Log::info("Cloning " . count($shops) . " shops for project $oldProjectId");
        foreach ($shops as $shop) {
            $oldShopId = $shop->shop_id;
            $newShop = (array) $shop;
            unset($newShop['shop_id']);
            $newShop['shop_projectid'] = $newProjectId;
            $newShop['shop_eventid'] = $newEventId;

            $newShopId = DB::table('shop')->insertGetId($newShop);
            $this->idMap['shop'][$oldShopId] = $newShopId;

            Log::info("Cloned shop: $oldShopId -> $newShopId (project_id=$newProjectId)");
            $this->cloneShopItems($oldShopId, $newShopId, $newEventId);
        }
    }

    protected function cloneShopItems($oldShopId, $newShopId, $newEventId)
    {
        $items = DB::table('shopitem')->where('shopitem_shopid', $oldShopId)->get();
        Log::info("Cloning " . count($items) . " shopitems for shop $oldShopId");
        foreach ($items as $item) {
            $newItem = (array) $item;
            unset($newItem['shopitem_id']);
            $newItem['shopitem_shopid'] = $newShopId;
            $newItem['shopitem_eventid'] = $newEventId;
            DB::table('shopitem')->insert($newItem);
        }
    }

    protected function cloneGuests($oldEventId, $newEventId)
    {
        $guests = DB::table('guest')->where('guest_eventid', $oldEventId)->get();
        Log::info("Cloning " . count($guests) . " guests for event $oldEventId");
        foreach ($guests as $guest) {
            $newGuest = (array) $guest;
            unset($newGuest['guest_id']);

            // Suffix ' Demo' to guest_firstname
            $newGuest['guest_firstname'] = $guest->guest_firstname . ' Demo';

            // Suffix '-demo' to the local part of the email
            $parts = explode('@', $guest->guest_email, 2);
            if (count($parts) === 2) {
                $newGuest['guest_email'] = $parts[0] . '-demo@' . $parts[1];
            } else {
                $newGuest['guest_email'] = $guest->guest_email . '-demo';
            }

            // Generate a new guest_token of the same length and charset
            $newGuest['guest_token'] = $this->generateSimilarToken($guest->guest_token);

            $newGuest['guest_eventid'] = $newEventId;
            DB::table('guest')->insert($newGuest);

            Log::info("Cloned guest: old_event=$oldEventId, new_event=$newEventId, name={$newGuest['guest_firstname']}, email={$newGuest['guest_email']}, token={$newGuest['guest_token']}");
        }
    }

    protected function generateSimilarToken($token)
    {
        $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = strlen($token);
        $newToken = '';
        for ($i = 0; $i < $length; $i++) {
            $newToken .= $charset[random_int(0, strlen($charset) - 1)];
        }
        return $newToken;
    }

    protected function cloneInstalls($oldEventId, $newEventId)
    {
        $installs = DB::table('install')->where('install_eventid', $oldEventId)->get();
        Log::info("Cloning " . count($installs) . " installs for event $oldEventId");
        foreach ($installs as $install) {
            $newInstall = (array) $install;
            unset($newInstall['install_id']);
            $newInstall['install_eventid'] = $newEventId;
            DB::table('install')->insert($newInstall);
        }
    }

    protected function cloneLookups($oldEventId, $newEventId)
    {
        $lookups = DB::table('lookup')->where('lookup_eventid', $oldEventId)->get();
        Log::info("Cloning " . count($lookups) . " lookups for event $oldEventId");
        foreach ($lookups as $lookup) {
            $newLookup = (array) $lookup;
            $newLookup['lookup_eventid'] = $newEventId;
            DB::table('lookup')->insert($newLookup);
        }
    }
}

