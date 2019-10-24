<?php

class LatestDiscussions {

	/**
	 * On parser setup
	 * @param $parser
	 */
	public static function onParserSetup( &$parser ) {
		$parser->setFunctionHook( 'displayalldiscussions', 'LatestDiscussions::displayAllDiscussions' );
	}

	/**
	 * Parser function called by {{#displayalldiscussions}}
	 * @param $parser
	 * @param $limit
	 * @return array
	 */
	public static function displayAllDiscussions( $parser, $limit = 10 ) {

		$ad = new LatestDiscussions();
		$html = $ad->renderAllDiscussions($limit);

		return [$html, 'noparse' => false, 'isHTML' => true];
	}

	/**
	 * Render discussions
	 * This function is called by a few extensions, do not remove
	 * @param int $limit
	 * @param int $offset
	 * @param string $filter
	 * @return string
	 */
   	public function renderAllDiscussions( $limit = 10, $offset = 0, $filter = 'default'){
		# Fetch all comment pages
		$pages = LatestDiscussionsQueries::getCommentPages( $limit + 1, $offset, $filter);

		# Return comments
		return $this->renderComments($pages);
	}

	/**
	 * Render discussions
	 * This function is called by CommentStreams
	 * @param Title $category
	 * @param int $limit
	 * @param int $offset
	 * @return string
	 */
	public function renderDiscussionsFromCategory(Title $category, $limit = 10, $offset = 0){
		# Fetch all comment pages
		$pages = LatestDiscussionsQueries::getCommentPagesByCategory($category, $limit, $offset);

		# Return comments
		return $this->renderComments($pages);
	}

	/**
	 * Render user's discussions
	 * This function is called by SocialProfile
	 * @param User $user
	 * @param int $limit
	 * @param int $offset
	 * @return string
	 */
	public function renderDiscussionsFromUser(User $user, $limit = 10, $offset = 0){
		#Fetch all comments posted by the specified user
		$pages = LatestDiscussionsQueries::getCommentPagesByUser($user, $limit, $offset);

		# Return comments
		return $this->renderComments($pages);
	}

	/**
	 * Render pages of comments
	 * @param $pages
	 * @return string
	 */
	public function renderComments($pages){
		global $wgOut;

		# Add CommentStreams style
		$wgOut->addModuleStyles( 'ext.CommentStreamsAllComments' );
		$wgOut->addModuleStyles( 'ext.CommentStreamsAllDiscussions' );

		# Display not found message if no comments
		if ( !$pages->valid() ) {
			return $this->renderNoCommentsMessage();
		}

		$html = '';
		foreach ( $pages as $page ) {
			# Create Comment instance
			$wikipage = WikiPage::newFromId( $page->page_id );
			$comment = Comment::newFromWikiPage( $wikipage );

			# If no Comment instance, go to the next comment
			if(is_null($comment)) continue;

			# We only want discussions
			if($comment->getParentId()){
				continue;
			}

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

				$categoryTitle = $associatedpage->getTitle()->getText();
				if(class_exists('CategoryManagerCore')){
					$title = Title::makeTitleSafe(NS_CATEGORY, $categoryTitle);
					$categoryTitle = CategoryManagerCore::getTranslatedCategoryTitle($title);
				}

				$commentUrl = $associatedpage->getTitle()->getFullURL() . '#cs-comment-'.$comment->getId();

				$html .= '<div class="row cs-disscussion cs-disscussion-transclude">';
				$html .=     '<div class="col-sm-2 col-xs-3"><div class="cs-nb-replies ' . $hasRepliesClass . $hasAnswerClass . '"><span class="cs-nb-replies-nb">' . $numReplies . '</span> '.wfMessage('commentstreams-alldiscussions-replies').'</div></div>';
				$html .=     '<div class="col-sm-7 col-xs-9">';
				$html .=        '<div class="cs-comment-title"><a href="'.$commentUrl.'">'.$comment->getCommentTitle().'</a></div>';
				$html .=        '<div class="cs-associated-page-name">'.Linker::link(\Title::newFromText($associatedpage->getTitle()->getPrefixedText()), $categoryTitle).'</div>';
				$html .=		'<div class="cs-comment-content">'.$comment->getWikitext().'</div>';
				$html .=     '</div>';
				$html .=     '<div class="col-sm-3 col-xs-12">';
				$html .=         '<div class="cs-comment-author-avatar"><img src="'.$comment->getAvatar().'" alt="" border="0" /></div>';
				$html .=         '<div class="cs-comment-author-creationdate-parent-div"><div class="cs-comment-author">'.$author.'</div>';
				$html .=         '<div class="cs-comment-creation-date">'.$comment->getCreationDate().'</div></div>';
				$html .=     '</div>';
				$html .= '</div><hr>';
			}
		}

		return $html;
	}

	/**
	 * Generate no comments message template
	 * @return string
	 */
	private function renderNoCommentsMessage() {
		$html = Html::openElement( 'p', [
				'class' => 'csall-message'
				] )
			. 	wfMessage( 'commentstreams-allcomments-nocommentsfound' )
			. 	Html::closeElement( 'p' );

		return $html;
	}
}