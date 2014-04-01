<h1>Subscription <?= $subscription['id'] ?></h1>

<ul>
	<li>Topic: <?= $subscription['topic'] ?></li>
	<li>Hub: <?= $subscription['hub'] ?></li>
	<li>Mode: <?= $subscription['mode'] ?> (<?= $subscription['intent_verified'] ? 'verified' : 'unverified' ?>)</li>
	<!--<li>Last pinged: <?= $subscription['last_pinged'] ?></li>
	<li>Last updated: <?= $subscription['last_updated'] ?></li>-->
	<li>Created: <?= $subscription['created'] ?></li>
</ul>

<h2>Recent Pings</h2>

<table>
	<thead>
		<tr>
			<th>ID</th>
			<th>Datetime</th>
			<th>Content Type</th>
			<th>Content</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($pings as $ping): ?>
		<tr>
			<td><a href="<?= $ping['url'] ?>"><?= $ping['id'] ?></a></td>
			<td><?= $ping['datetime'] ?></td>
			<td><?= $ping['content_type'] ?></td>
			<td><?= strlen($ping['content']) ?> bytes of content</td>
		</tr>
		<?php endforeach ?>
	</tbody>
</table>
