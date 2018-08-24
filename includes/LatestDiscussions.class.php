<?php

class LatestDiscussions {

   public static function onParserSetup( &$parser ) {

		$parser->setFunctionHook( 'displayalldiscussions', 'LatestDiscussions::displayAllDiscussions' );
   }

   public static function displayAllDiscussions( $parser, $limit ) {

    	global $wgScriptPath, $wgParser, $wgOut, $wgTitle;

		$wgOut->addModuleStyles( 'ext.CommentStreamsAllComments' );
		$wgOut->addModuleStyles( 'ext.CommentStreamsAllDiscussions' );

		$offset = 0;

		$pages = self::getCommentPages( $limit + 1, $offset );

		if ( !$pages->valid() ) {
			$this->displayMessage(
				wfMessage( 'commentstreams-allcomments-nocommentsfound' )
			);
			return;
		}

		$index = 0;
		$more = false;

		$html = "";

		foreach ( $pages as $page ) {

			if ( $index < $limit ) {
				$wikipage = WikiPage::newFromId( $page->page_id );
				$comment = Comment::newFromWikiPage( $wikipage );
				if($comment->getParentId()){
					//we only want discussions
					continue;
				}
				if ( !is_null( $comment ) ) {
					$pagename = $comment->getWikiPage()->getTitle()->getPrefixedText();
					$associatedpageid = $comment->getAssociatedId();
					$associatedpage = WikiPage::newFromId( $associatedpageid );
					if ( !is_null( $associatedpage ) ) {
						$associatedpagename =
							'[[' . $associatedpage->getTitle()->getPrefixedText() . ']]';
						$author = $comment->getUser();
						if ( $author->isAnon() ) {
							$author = '<i>' . wfMessage( 'commentstreams-author-anonymous' )
								. '</i>';
						} else {
							$author = $author->getName();
						}
						$modificationdate = $comment->getModificationDate();
						if ( is_null( $modificationdate ) ) {
							$lasteditor = '';
						} else {
							$lasteditor =
								User::newFromId( $wikipage->getRevision()->getUser() );
							if ( $lasteditor->isAnon() ) {
								$lasteditor = '<i>' .
									wfMessage( 'commentstreams-author-anonymous' ) . '</i>';
							} else {
								$lasteditor = $lasteditor->getName();
							}
						}

						$numReplies = $comment->getNumReplies();
						$hasRepliesClass = $numReplies > 0 ? 'has-replies' : '';
						$hasAnswerClass = $comment->isSolved() ? 'has-answer' : '';

						$html .= '<div class="row cs-disscussion">';
						$html .=     '<div class="col-sm-2 col-xs-3"><div class="cs-nb-replies ' . $hasRepliesClass . $hasAnswerClass . '"><span class="cs-nb-replies-nb">' . $numReplies . '</span> '.wfMessage('commentstreams-alldiscussions-replies').'</div></div>';
						$html .=     '<div class="col-sm-7 col-xs-9">';
						$html .=        '<div class="cs-comment-title"><a href="'.$associatedpage->getTitle()->getPrefixedText().'#cs-comment-'.$comment->getId().'">'.$comment->getCommentTitle().'</a></div>';
						$html .=        '<div class="cs-associated-page-name">'.Linker::link(\Title::newFromText($associatedpage->getTitle()->getPrefixedText()), $associatedpage->getTitle()->getPrefixedText()).'</div>';
						$html .=		'<div class="cs-comment-content">'.$comment->getWikitext().'</div>';
						$html .=     '</div>';
						$html .=     '<div class="col-sm-3 col-xs-12">';
						$html .=         '<div class="cs-comment-author-avatar"><img src="'.$comment->getAvatar().'" alt="" border="0" /></div>';
						$html .=         '<div class="cs-comment-author-creationdate-parent-div"><div class="cs-comment-author">'.$author.'</div>';
						$html .=         '<div class="cs-comment-creation-date">'.$comment->getCreationDate().'</div></div>';
						$html .=     '</div>';
						$html .= '</div>';
						$index ++;
					}
				}
			} else {
				$more = true;
			}
		}

       return array( $html, 'noparse' => false, 'isHTML' => true);

   }

	private function displayMessage( $message ) {

		global $wgOut;	

		$html = Html::openElement( 'p', [
				'class' => 'csall-message'
			] )
			. $message
			. Html::closeElement( 'p' );
		$wgOut->addHtml( $html );
	}

	private static function getCommentPages( $limit, $offset, $filter = 'default' ) {

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
}