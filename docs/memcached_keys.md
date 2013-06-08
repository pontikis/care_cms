Memcached keys
==============

CMS common (class cms_common)
----------

~~~~~~~~~~~~~{.php}
$recent_topics_key = 'care_recent_topics';
~~~~~~~~~~~~~

It holds an array with:
- site recent topics (id, url, title)


Care topic (class topic_retrieve)
----------

~~~~~~~~~~~~~{.php}
$topic_key = 'care_topic_' . $topic_id;
~~~~~~~~~~~~~

It holds an array with:
- topic record (table topics). Impressions are increasing per request.

~~~~~~~~~~~~~{.php}
$topic_author_key = 'care_author_' . $author_id;
~~~~~~~~~~~~~

It holds an array with:
- author username and fullname for an author

~~~~~~~~~~~~~{.php}
$topic_category_key = 'care_topic_category_' . $category_id;
~~~~~~~~~~~~~

It holds an array with:
- category name (upper case - no accents) and url of category

Care category (class category_retrieve)
------------

~~~~~~~~~~~~~{.php}
$category_key = 'care_category_' . sha1($category_url);
~~~~~~~~~~~~~

It holds an array with:
- category record (table categories)
- relative categories
- category toc (hierarchical view only) as html
- category subcategories (hierarchical view only)

Care tag (class tag_retrieve)
---------

Memcached is not used here.

Care member (class member_retrieve)
-----------

~~~~~~~~~~~~~{.php}
$member_key = 'care_member_' . sha1($member_url);
~~~~~~~~~~~~~

It holds an array with:
- member record (table users). Profile views are increasing per request.

~~~~~~~~~~~~~{.php}
$member_recent_topics_key = 'care_member_recent_topics_' . $member_id;
~~~~~~~~~~~~~

It holds an array with:
- member recent topics (except news hellas and news world)

~~~~~~~~~~~~~{.php}
$member_topics_count_key = 'care_member_topics_count_' . $member_id;
~~~~~~~~~~~~~

It holds:
- member topics count (except news hellas and news world)

~~~~~~~~~~~~~{.php}
$member_recent_bookmarks_key = 'care_member_recent_bookmarks_' . $member_id;
~~~~~~~~~~~~~

It holds an array with:
- member recent bookmarks (id, url, title) and member recent public bookmarks

~~~~~~~~~~~~~{.php}
$member_bookmarks_count_key = 'care_member_bookmarks_count_' . $member_id;
~~~~~~~~~~~~~

It holds an array with:
- member bookmarks count and member public bookmarks count