<div class="wrap">
<h2>Paypal Target Meter Tools</h2>
<table width="80%">
	<tr>
		<th>Widget ID</th>
		<th>Action</th>
	</tr>
<?php
global $wp_registered_widget_controls;
$pptm_widget_count = 0;
$sidebars_widgets = wp_get_sidebars_widgets();
if ( is_array($sidebars_widgets) ) {
	foreach ( $sidebars_widgets as $sidebar => $widgets ) {
		if ('wp_inactive_widgets' == $sidebar )
			continue;
		if ( is_array($widgets) ) {
			foreach ( $widgets as $widget ) {
				if(_get_widget_id_base($widget) == "paypal_target_meter_widget") {
?>
					<tr>
						<td><?php echo $widget; ?></td>
						<td><a href="?page=<?php echo $_GET['page']; ?>&debug=true&widget_id=<?php echo $widget; ?>">Debug</a></td>
					</tr>
<?php
					$pptm_widget_count++;
				}
			}
		}
	}
}
if ($pptm_widget_count == 0) {
?>
				<tr><td colspan=2>No Configured Widgets</td></tr>
<?php
}
?>
</table>

<?php
if (array_key_exists('debug',$_GET) and array_key_exists('widget_id',$_GET) and $_GET['debug'] == 'true')
{
?>
<h4>Debug output for <?php echo $_GET['widget_id']; ?></h4>
<textarea style="width: 80%; height: 100px;">
<?php $wp_registered_widget_controls[$_GET['widget_id']]['callback'][0]->get_paypal_totals(1); ?>
</textarea>
<?php
}
?> 

</div>