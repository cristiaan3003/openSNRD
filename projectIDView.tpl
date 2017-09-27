{**
 * plugins/generic/openSNRD/projectIDView.tpl
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * OpenSNRD projectID view
 *
 *}
<!-- OpenSNRD -->
<div id="openSNRD">
<h4>{translate key="plugins.generic.openSNRD.metadata"}</h4>
<table width="100%" class="data">
	<tr valign="top">
		<td rowspan="2" width="20%" class="label">{translate key="plugins.generic.openSNRD.projectID"}</td>
		<td width="80%" class="value">{$submission->getData('projectID')|escape|default:"&mdash;"}</td>
	</tr>
</table>
</div>
<!-- /OpenSNRD -->

