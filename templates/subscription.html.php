<h1>Subscription <?= $subscription['id'] ?></h1>

Topic: <?= $subscription['topic'] ?>
Hub: <?= $subscription['hub'] ?>
Mode: <?= $subscription['mode'] ?> (<?= $subscription['intent_verified'] ? 'verified' : 'unverified' ?>
Last pinged: <?= $subscription['last_pinged'] ?>
Last updated: <?= $subscription['last_updated'] ?>
Created: <?= $subscription['created'] ?>

<h2>Recent Pings</h2>

<table>
	
</table>
