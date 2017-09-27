{**
 * plugins/generic/openSNRD/snrdIDEdit.tpl
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Edit OpenSNRD snrdID 
 *
 *}
<!-- OpenSNRD -->
<div id="openSNRD">
<h3>{translate key="plugins.generic.openSNRD.metadata"}</h3>
<table width="100%" class="data">
<tr valign="top">
	<td rowspan="2" width="20%" class="label">{fieldLabel name="snrdID" key="plugins.generic.openSNRD.snrdID"}</td>
	<td width="80%" class="value"><input type="text" class="textField" name="snrdID" id="snrdID" value="{$snrdID|escape}" size="5" maxlength="10" /></td>
</tr>
<tr valign="top">
	<td><span class="instruct">{translate key="plugins.generic.openSNRD.snrdID.description"}</span></td>
</tr>
</table>
</div>
<div class="separator"></div>
<!-- /OpenSNRD -->

