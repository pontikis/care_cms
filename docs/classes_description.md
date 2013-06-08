Classes description
====================


Class app_common
-----------------

"Application wide" functionality
- app_common::init "application" initilization
- app_common::error_handler custom error handler
- various functions

Class data_source
-----------------

- Database connection functionality (data_source::db_connect data_source::db_disconnect)
- Memcached functionality (data_source::mc_init data_source::pull_from_memcached data_source::push_to_memcached)

Class cms_common
-----------------

Common CMS functionality
- cms_common::get_topics_list Create topics list with various criteria
- cms_common::get_recent_topics Get site recent posts

Class topic_retrieve
-----------------

- topic_retrieve::get_topic creates and returns an array with all data needed for post page (http://www.care.gr/post-id/post-url)

Class category_retrieve
-----------------

- category_retrieve::get_category creates and returns an array with all data needed for category page (http://www.care.gr/category-url)

Class tag_retrieve
-----------------

- tag_retrieve::get_tag creates and returns an array with all data needed for tag page (http://www.care.gr/tag-url)

Class member_retrieve
-----------------

- member_retrieve::get_member creates and returns an array with all data needed for post page (http://www.care.gr/member-url)
- member_retrieve::get_member_bookmarks_page creates and returns an array with all data needed for member bookmarks page (http://www.care.gr/bookmarks/member-url)
- member_retrieve::get_member_topics_page creates and returns an array with all data needed for member topics page (http://www.care.gr/posts/member-url)

