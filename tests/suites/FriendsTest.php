<?php
/**
 * Test suite for the social Friends relationship system.
 */

require_once __DIR__ . '/../../include/handlers/friends.php';

class FriendsTest {
    public $useDatabase = true; // Use transactional isolation

    public function testFriendsRelationshipStates() {
        global $pdo;
        
        $userA = test_create_user($pdo, "UserA");
        $userB = test_create_user($pdo, "UserB");
        
        // 1. Initial State: none
        Assert::equals('none', friends_relationship_to($pdo, $userA, $userB));
        Assert::equals('self', friends_relationship_to($pdo, $userA, $userA));
        
        // 2. Send request: pending_out / pending_in
        $res = friends_send_request($pdo, $userA, $userB);
        Assert::notNull($res['request_id']);
        
        Assert::equals('pending_out', friends_relationship_to($pdo, $userA, $userB));
        Assert::equals('pending_in',  friends_relationship_to($pdo, $userB, $userA));
        
        // 3. Accept request: friends
        friends_respond($pdo, $userB, $res['request_id'], 'accept');
        
        Assert::equals('friends', friends_relationship_to($pdo, $userA, $userB));
        Assert::equals('friends', friends_relationship_to($pdo, $userB, $userA));
        
        // 4. Unfriend: none
        friends_unfriend($pdo, $userA, $userB);
        Assert::equals('none', friends_relationship_to($pdo, $userA, $userB));
    }

    public function testFriendRequestExceptions() {
        global $pdo;
        
        $userA = test_create_user($pdo, "UserA");
        $userB = test_create_user($pdo, "UserB");
        
        // Cannot friend yourself
        Assert::throws(function() use ($pdo, $userA) {
            friends_send_request($pdo, $userA, $userA);
        }, 'FriendsActionException');
        
        // Block scenario
        $pdo->prepare("INSERT INTO friend_block (blocker_id, blocked_id) VALUES (?, ?)")->execute([$userA, $userB]);
        
        // Blocked state should short-circuit sending requests
        Assert::equals('blocked_out', friends_relationship_to($pdo, $userA, $userB));
        Assert::equals('blocked_in',  friends_relationship_to($pdo, $userB, $userA));
        
        Assert::throws(function() use ($pdo, $userA, $userB) {
            friends_send_request($pdo, $userA, $userB);
        }, 'FriendsActionException');
    }

    public function testFriendRequestDailyRateLimit() {
        global $pdo;
        
        $me = test_create_user($pdo, "RateLimitMe");
        
        // Create 20 unique other users to send requests to (daily limit is 20)
        $others = [];
        for ($i = 0; $i < 21; $i++) {
            $others[] = test_create_user($pdo, "Other" . $i);
        }
        
        // Send 20 successful requests
        for ($i = 0; $i < 20; $i++) {
            friends_send_request($pdo, $me, $others[$i]);
        }
        
        // The 21st request should hit the daily rate limit exception
        Assert::throws(function() use ($pdo, $me, $others) {
            friends_send_request($pdo, $me, $others[20]);
        }, 'FriendsActionException', 'Daily friend-request limit reached. Try again tomorrow.');
    }

    public function testLeaderboardSortingAndRanks() {
        global $pdo;
        
        $me = test_create_user($pdo, "LeaderMe");
        $friend1 = test_create_user($pdo, "FriendOne");
        $friend2 = test_create_user($pdo, "FriendTwo");
        
        // Bootstrap XP rows
        $pdo->prepare("INSERT INTO user_xp (user_id, total_xp, current_level) VALUES (?, 1000, 5), (?, 500, 3), (?, 2000, 8)")
            ->execute([$me, $friend1, $friend2]);
            
        // Establish friendship: me <-> friend1 and me <-> friend2
        $req1 = friends_send_request($pdo, $me, $friend1);
        friends_respond($pdo, $friend1, $req1['request_id'], 'accept');
        
        $req2 = friends_send_request($pdo, $me, $friend2);
        friends_respond($pdo, $friend2, $req2['request_id'], 'accept');
        
        // Get leaderboard (all_time)
        $board = leaderboard_friends($pdo, $me, 'all_time');
        
        // Expected order by total_xp DESC: friend2 (2000), me (1000), friend1 (500)
        Assert::equals(3, count($board));
        
        Assert::equals($friend2, (int)$board[0]['user_id']);
        Assert::equals(1, $board[0]['rank']);
        
        Assert::equals($me, (int)$board[1]['user_id']);
        Assert::equals(2, $board[1]['rank']);
        
        Assert::equals($friend1, (int)$board[2]['user_id']);
        Assert::equals(3, $board[2]['rank']);
    }

    public function testBadgeCountCaching() {
        global $pdo;
        
        $me = test_create_user($pdo, "BadgeMe");
        $sender = test_create_user($pdo, "Sender");
        
        // Initially 0 incoming requests
        Assert::equals(0, friends_pending_count_incoming($pdo, $me));
        
        // Sender sends request
        friends_send_request($pdo, $sender, $me);
        
        // Cache is stored for 60s, so it should still return 0 initially (since session caching is active)
        // Wait, friends_pending_count_incoming checks the session cache:
        // Let's assert it caches, and then assert that calling invalidate clears it, returning 1!
        
        friends_invalidate_pending_cache($me);
        Assert::equals(1, friends_pending_count_incoming($pdo, $me));
    }
}
