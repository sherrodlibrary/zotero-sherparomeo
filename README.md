Attempts to add tags for publisher archiving policies from Sherpa/RoMEo (http://www.sherpa.ac.uk/romeo/) to items
in a Zotero (http://www.zotero.org/) library based on ISSN or journal title. (Demo from code4lib 2012 lightning talk).
Uses Zend Framework (http://framework.zend.com/)  HTTP client.

Based on https://github.com/rshanrath/zotero-sherparomeo

Import tool to import citation data from Sherpa/Romeo to Zotero.

Updated to use most recent version of Zend HTTP client (circa 2017) via composer.
Supports zotero groups and limits to updating the most recently modified records with the collection_type and limit arguments.
zotero_sherpa.php now supports arguments provided as a query string in the format:
` zotero_sherpa.php?userid=&zotero_key=&sherpa_romeo_key=&collection_type=user&limit= `

Also works around some minor bugs in the Zotero API (or web interface, I can't tell which) that can cause unexpected, 
arbitrarily nested json elements to cause the parent elements to be remove when saving in the zotero web interface.

The provided (rather clunky) web interface expects a ` run_list.sh ` file to exist in the working directory of the application and is executable by the user that php runs as (normally the apache or nginx user). This file should contain one or more invocations of zotero_sherpa.php such as:

` php zotero_sherpa.php [group/user id] [zotero key] [sherpa_romeo_key] [collection_type (user or group)] [limit] `

If using the web interface, using an IP white-list, or HTTP basic authentication (with HTTPS!) should be considered to prevent well meaning or nefarious crawlers from creating unnecessary server load for zotero, sherparomeo, or your own hosting platform.    
