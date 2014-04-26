<h1>Subscriptions</h1>

<form action="<?= $newSubscriptionUrl ?>" method="post">
	<p><label>URL: <input type="url" name="url" /></label> <button type="submit">Subscribe</button></p>
</form>

<form action="<?= $crawlUrl ?>" method="post">
	<p><label>URL: <input type="url" name="url" /></label> <button type="submit">Subscribe and Crawl</button></p>
</form>

<table>
	<thead>
		<tr>
			<th>ID</th>
			<th>Topic</th>
			<th>Hub</th>
			<th>Mode (verified)</th>
			<th>Last Pinged</th>
			<th>Last Updated</th>
			<th>Created</th>
		</tr>
	</thead>
	<tbody>
	
<?php foreach ($subscriptions as $subscription): ?>
	<tr>
		<td><a href="<?= $subscription['url'] ?>"><?= $subscription['id'] ?></a></td>
		<td><?= $subscription['topic'] ?></td>
		<td><?= $subscription['hub'] ?></td>
		<td><?= $subscription['mode'] ?> (<?= $subscription['intent_verified'] ? 'yup' : 'nope' ?>)</td>
		<td><?= $subscription['last_pinged'] ?></td>
		<td><?= $subscription['last_updated'] ?></td>
		<td><?= $subscription['created'] ?></td>
	</tr>
<?php endforeach ?>
	</tbody>
</table>