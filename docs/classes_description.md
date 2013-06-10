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

- Database connection functionality. Currently only mysqli is supported (data_source::db_connect data_source::db_disconnect)
- Memcached functionality (data_source::mc_init data_source::pull_from_memcached data_source::push_to_memcached)

Class cms_common
-----------------

Common CMS functionality
- cms_common::get_topics_list Create topics list with various criteria
- cms_common::get_recent_topics Get site recent posts

Class topic_retrieve
-----------------

- topic_retrieve::get_topic creates and returns an array with all data needed for post page (%http://www.care.gr/post/post-id/post-url)

Class category_retrieve
-----------------

- category_retrieve::get_category creates and returns an array with all data needed for category page (%http://www.care.gr/category/category-url)

Class tag_retrieve
-----------------

- tag_retrieve::get_tag creates and returns an array with all data needed for tag page (%http://www.care.gr/tag/tag-url)

Class member_retrieve
-----------------

- member_retrieve::get_member creates and returns an array with all data needed for post page (%http://www.care.gr/member-url)
- member_retrieve::get_member_page_topics creates and returns an array with member topics page (%http://www.care.gr/member-url/posts)
- member_retrieve::get_member_page_bookmarks creates and returns an array with member bookmarks page (%http://www.care.gr/member-url/bookmarks)

Class site_search
-----------------

- site_search::quick_search creates json for jquery-ui autocomplete search box