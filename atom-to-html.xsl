<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet 
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

<xsl:output method="html"/>

<xsl:template match="/atom:feed">
<html>
	<head>
		<title><xsl:value-of select="atom:title"/></title>
		<link rel="stylesheet" type="text/css" href="css/default.css"/>
		<script language="javascript" src="lib/xsl_mop-up.js"></script>
	</head>

	<body onload="go_decoding()" class="ttrss_utility">

		<div id="cometestme" style="display:none;">
			<xsl:text disable-output-escaping="yes">&amp;amp;</xsl:text>
		</div>

		<div class="rss">

		<h1><xsl:value-of select="atom:title"/></h1>

		<p class="description">This feed has been exported from
			<a target="_new" class="extlink" href="http://tt-rss.org">Tiny Tiny RSS</a>.
			It contains the following items:</p>

		<xsl:for-each select="atom:entry">
			<h2><a target="_new" href="{atom:link/@href}"><xsl:value-of select="atom:title"/></a></h2>

			<div name="decodeme" class="content">
				<xsl:value-of select="atom:content" disable-output-escaping="yes"/>
			</div>

			<xsl:if test="enclosure">
				<p><a href="{enclosure/@url}">Extra...</a></p>
			</xsl:if>


		</xsl:for-each>

		</div>

  </body>
 </html>
</xsl:template>

</xsl:stylesheet>

