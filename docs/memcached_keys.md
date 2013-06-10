Memcached keys
==============

CMS common (class cms_common)
----------------------------

~~~~~~~~~~~~~{.php}
$recent_topics_key = 'care_recent_topics';
~~~~~~~~~~~~~

It holds an array with:
- site recent topics (id, url, title)


Care topic (class topic_retrieve)
--------------------------------

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
--------------------------------------

~~~~~~~~~~~~~{.php}
$category_key = 'care_category_' . sha1($category_url);
~~~~~~~~~~~~~

It holds an array with:
- category record (table categories)
- relative categories
- category toc (hierarchical view only) as html
- category subcategories (hierarchical view only)

~~~~~~~~~~~~~{.php}
$category_topics_count_key = 'care_category_topics_count_' . $category_id;
~~~~~~~~~~~~~

It holds an integer:
- category topics count (only for list_mode = 1)

~~~~~~~~~~~~~{.php}
$popular_in_category_key = 'care_popular_in_category_' . sha1($this->category_url);
~~~~~~~~~~~~~

It holds an array with:
- popular topics in category. Expiration after 15 min (900 sec). [Global option](@ref settings.dist.php): $care_conf['opt_popular_in_category_expiration']


Care tag (class tag_retrieve)
----------------------------

~~~~~~~~~~~~~{.php}
$tag_topics_count_key = 'care_tag_topics_count_' . sha1($this->tag_url);
~~~~~~~~~~~~~

It holds an integer:
- tag topics count

~~~~~~~~~~~~~{.php}
$popular_with_tag_key = 'care_popular_with_tag_' . sha1($tag_url);
~~~~~~~~~~~~~

It holds an array with:
- popular topics with tag. Expiration after 15 min (900 sec). [Global option](@ref settings.dist.php): $care_conf['opt_popular_with_tag_expiration']

Care member (class member_retrieve)
----------------------------------

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

It holds an integer:
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

~~~~~~~~~~~~~{.php}
$member_comment_count_key = 'care_member_comments_count_' . $member_id;
~~~~~~~~~~~~~

It holds an integer:
- member comments count

~~~~~~~~~~~~~{.php}
$popular_by_author_key = 'care_popular_by_author_' . $member_id;
~~~~~~~~~~~~~

It holds an array with:
- popular topics by author. Expiration after 15 min (900 sec). [Global option](@ref settings.dist.php): $care_conf['opt_popular_by_author_expiration']

