<?php
/**
 * Created by PhpStorm.
 * User: Brendan
 * Date: 08/10/2018
 * Time: 15:59
 */

class LatestDiscussionsQueries
{
	/**
	 * Return latest comment pages id
	 *
	 * @param $limit
	 * @param $offset
	 * @param string $filter
	 * @return bool|\Wikimedia\Rdbms\IResultWrapper|\Wikimedia\Rdbms\ResultWrapper
	 */
	public static function getCommentPages($limit, $offset, $filter = 'default' ) {

		$dbr = wfGetDB( DB_REPLICA );

		switch ($filter) {
			case "mosthelpful":
				$subQuery = $dbr->selectSQLText(
					'cs_votes',
					'COUNT(*)',
					[
						'cst_v_page_id = cst_page_id',
						'cst_v_vote = 1'
					],
					__METHOD__
				);

				$pages = $dbr->select(
					[
						'cs_comment_data',
						'page',
						'revision'
					],
					[ 'page_id' => 'cst_page_id', 'numUpVotes' => "($subQuery)" ],
					[
						'cst_page_id = page_id',
						'page_latest = rev_id',
						'cst_parent_page_id IS NULL'
					],
					__METHOD__ ,
					[
						'ORDER BY' => 'numUpVotes DESC' ,
						'LIMIT' => $limit,
						'OFFSET' => $offset
					]
				);
				break;
			case "unanswered":
				$subQuery = $dbr->selectSQLText(
					['reply' => 'cs_comment_data'],
					'1',
					[
						'reply.cst_parent_page_id = discussion.cst_page_id'
					],
					__METHOD__
				);

				$pages = $dbr->select(
					[
						'discussion' => 'cs_comment_data',
						'page',
						'revision'
					],
					[ 'page_id' ],
					[
						'NOT EXISTS (' . $subQuery . ')',
						'cst_page_id = page_id',
						'page_latest = rev_id',
						'cst_parent_page_id IS NULL'
					],
					__METHOD__,
					[
						'LIMIT' => $limit,
						'OFFSET' => $offset
					]
				);
				break;
			case "mostanswered":
				$subQuery = $dbr->selectSQLText(
					['reply' => 'cs_comment_data'],
					'COUNT(*)',
					[
						'reply.cst_parent_page_id = discussion.cst_page_id'
					],
					__METHOD__
				);

				$pages = $dbr->select(
					[ 'discussion' => 'cs_comment_data', 'page', 'revision' ],
					[ 'page_id', 'numReplies' => "($subQuery)" ],
					[
						'discussion.cst_page_id = page_id',
						'page_latest = rev_id',
						'cst_parent_page_id IS NULL'
					],
					__METHOD__,
					[
						'ORDER BY' => 'numReplies DESC',
						'LIMIT' => $limit,
						'OFFSET' => $offset
					]
				);
				break;
			case "solved":
				if ( $GLOBALS['wgCommentStreamsEnableAccepting'] ){
					$subQuery = $dbr->selectSQLText(
						'cs_accepted_answer',
						'1',
						[
							'cst_discussion_id = discussion.cst_page_id',
							'cst_answer_id IS NOT NULL'						],
						__METHOD__
					);

					$pages = $dbr->select(
						[ 'discussion' => 'cs_comment_data', 'page', 'revision' ],
						[ 'page_id' ],
						[
							'discussion.cst_page_id = page_id',
							'page_latest = rev_id',
							"EXISTS ($subQuery)",
							'cst_parent_page_id IS NULL'
						],
						__METHOD__,
						[
							'ORDER BY' => 'rev_timestamp DESC',
							'LIMIT' => $limit,
							'OFFSET' => $offset
						]
					);
					break;
				}
			case "unsolved":
				if ( $GLOBALS['wgCommentStreamsEnableAccepting'] ){
					$subQuery = $dbr->selectSQLText(
						'cs_accepted_answer',
						'1',
						[
							'cst_discussion_id = discussion.cst_page_id',
							'cst_answer_id IS NOT NULL',
							'cst_parent_page_id IS NULL'
						],
						__METHOD__
					);

					$pages = $dbr->select(
						[ 'discussion' => 'cs_comment_data', 'page', 'revision' ],
						[ 'page_id' ],
						[
							'discussion.cst_page_id = page_id',
							'page_latest = rev_id',
							"NOT EXISTS ($subQuery)"
						],
						__METHOD__,
						[
							'ORDER BY' => 'rev_timestamp DESC',
							'LIMIT' => $limit,
							'OFFSET' => $offset
						]
					);
					break;
				}
			case "newest":
			default:
				$pages = $dbr->select(
					[
						'cs_comment_data',
						'page',
						'revision'
					],
					[
						'page_id'
					],
					[
						'cst_page_id = page_id',
						'page_latest = rev_id',
						'cst_parent_page_id IS NULL'
					],
					__METHOD__,
					[
						'ORDER BY' => 'rev_timestamp DESC' ,
						'LIMIT' => $limit,
						'OFFSET' => $offset
					]
				);
		}

		return $pages;
	}

	/**
	 * Return comments posted on a specific category
	 *
	 * @param Title $category
	 * @param $limit
	 * @param $offset
	 * @return mixed
	 */
	public static function getCommentPagesByCategory(Title $category, $limit, $offset ){
		$dbr = wfGetDB( DB_REPLICA );

		$pages = $dbr->select(
			[
				'cs_comment_data',
				'page',
				'revision',
				'categorylinks'
			],
			[
				'cst_page_id AS page_id'
			],
			[
				'cl_to' => $category->getText(),
			],
			__METHOD__,
			[
				'ORDER BY' => 'rev_timestamp DESC' ,
				'LIMIT' => $limit,
				'OFFSET' => $offset
			],
			[
				'page' => [
					'INNER JOIN',
					['cs_comment_data.cst_assoc_page_id = page.page_id']
				],
				'revision' => [
					'INNER JOIN',
					['page.page_latest = revision.rev_id']
				],
				'categorylinks' => [
					'INNER JOIN',
					['page.page_id = cl_from']
				]
			]
		);

		return $pages;
	}
}