<?php
/**
 * Ticket Functions
 *
 * @package     KBS
 * @subpackage  Replies/Functions
 * @copyright   Copyright (c) 2017, Mike Howard
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Retrieve all ticket replies for the ticket.
 *
 * @since	1.0
 * @param	int		$ticket_id		The Ticket ID.
 * @param	arr		$args			See @get_posts
 * @return	obj|false
 */
function kbs_get_replies( $ticket_id = 0, $args = array() )	{
	if ( empty( $ticket_id ) )	{
		return false;
	}

	$ticket = new KBS_Ticket( $ticket_id );

	return $ticket->get_replies( $args );
} // kbs_get_replies

/**
 * Retrieve ticket reply count.
 *
 * @since	1.0
 * @param	int		$ticket_id		The Ticket ID.
 * @return	int
 */
function kbs_get_reply_count( $ticket_id )	{
	$ticket = new KBS_Ticket( $ticket_id );

	return $ticket->get_reply_count();
} // kbs_get_reply_count

/**
 * Whether or not an agent has replied to a ticket.
 *
 * @since	1.0
 * @param	int			$ticket_id		The Ticket ID.
 * @return	obj|false	
 */
function kbs_ticket_has_agent_reply( $ticket_id )	{
	$reply_args = array(
		'posts_per_page' => 1,
		'meta_query'     => array(
			'relation'    => 'AND',
			array(
				'key'     => '_kbs_reply_agent_id',
				'compare' => 'EXISTS'
			),
			array(
				'key'     => '_kbs_reply_agent_id',
				'value'   => '0',
				'compare' => '!='
			)
		)
	);

	return kbs_get_replies( $ticket_id, $reply_args );
} // kbs_ticket_has_agent_reply

/**
 * Retrieve the last reply for the ticket.
 *
 * @since	1.0
 * @uses	kbs_get_replies()
 * @param	int		$ticket_id		The Ticket ID.
 * @param	arr		$args			See @get_posts
 * @return	obj|false
 */
function kbs_get_last_reply( $ticket_id, $args = array() )	{
	$args['posts_per_page'] = 1;

	$reply = kbs_get_replies( $ticket_id, $args );

	if ( $reply )	{
		return $reply[0];
	}

	return $reply;
} // kbs_get_last_reply

/**
 * Gets the ticket reply HTML.
 *
 * @since	1.0
 * @param	obj|int	$reply		The reply object or ID
 * @param	int		$ticket_id	The ticket ID the reply is connected to
 * @return	str
 */
function kbs_get_reply_html( $reply, $ticket_id = 0 ) {

	if ( is_numeric( $reply ) ) {
		$reply = get_post( $reply );
	}

	$author      = kbs_get_reply_author_name( $reply, true );
	$date_format = get_option( 'date_format' ) . ', ' . get_option( 'time_format' );
	$files       = kbs_ticket_has_files( $reply->ID );
	$file_count  = ( $files ? count( $files ) : false );

	$create_article_link = add_query_arg( array(
		'kbs-action' => 'create_article',
		'ticket_id'  => $ticket_id,
		'reply_id'   => $reply->ID
	), admin_url() );

	$create_article_link = apply_filters( 'kbs_create_article_link', $create_article_link, $ticket_id, $reply );

    $actions = array(
        'read_reply'     => '<a href="#" class="toggle-view-reply-option-section">' . __( 'View Reply', 'kb-support' ) . '</a>',
        'create_article' => '<a href="' . $create_article_link . '" class="toggle-reply-option-create-article">' . sprintf( __( 'Create %s', 'kb-support' ), kbs_get_article_label_singular() ) . '</a>'
    );

    $actions = apply_filters( 'kbs_ticket_replies_actions', $actions, $reply );

    $icons   = array();

    if ( false === strpos( $author, __( 'Customer', 'kb-support' ) ) )  {
        $is_read = kbs_reply_is_read( $reply->ID );
        if ( $is_read )  {
            $icons['is_read'] = sprintf(
                '<span class="dashicons dashicons-visibility" title="%s %s"></span>',
                __( 'Read by customer on', 'kb-support' ),
                date_i18n( $date_format, strtotime( $is_read ) )
            );
        } else  {
            $icons['not_read'] = sprintf(
                '<span class="dashicons dashicons-hidden" title="%s"></span>',
                __( 'Customer has not read', 'kb-support' )
            );
        }
    }

    if ( $file_count )  {
        $icons['files'] = sprintf(
            '<span class="dashicons dashicons-media-document" title="%s"></span>',
            $file_count . ' ' . _n( 'attached file', 'attached files', $file_count, 'kb-support' )
        );
    }

    $icons   = apply_filters( 'kbs_ticket_replies_icons', $icons, $reply );

    ob_start(); ?>

    <div class="kbs-replies-row-header">
        <span class="kbs-replies-row-title">
            <?php echo apply_filters( 'kbs_replies_title', sprintf( __( '%s by %s', 'kb-support' ), date_i18n( $date_format, strtotime( $reply->post_date ) ), $author ), $reply ); ?>
        </span>

        <span class="kbs-replies-row-actions">
            <?php echo implode( ' ', $icons ); ?>
			<?php echo implode( '&nbsp;&#124;&nbsp;', $actions ); ?>
        </span>
    </div>

    <div class="kbs-replies-content-wrap">
        <div class="kbs-replies-content-sections">
        	<?php do_action( 'kbs_before_reply_content_section', $reply ); ?>
            <div id="kbs-reply-option-section-<?php echo $reply->ID; ?>" class="kbs-replies-content-section">
                <?php do_action( 'kbs_replies_before_content', $reply ); ?>
                <?php echo wpautop( $reply->post_content ); ?>
                <?php do_action( 'kbs_replies_content', $reply ); ?>
            </div>
            <?php do_action( 'kbs_after_reply_content_section', $reply ); ?>
            <?php if ( $files ) : ?>
                <div class="kbs-replies-files-section">
                	<?php do_action( 'kbs_replies_before_files', $reply ); ?>
                    <ol>
                        <?php foreach( $files as $file ) : ?>
                            <li>
                            	<a href="<?php echo wp_get_attachment_url( $file->ID ); ?>" target="_blank">
									<?php echo basename( get_attached_file( $file->ID ) ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php do_action( 'kbs_replies_after_files', $reply ); ?>
                </div>
            <?php endif; ?>
            <?php do_action( 'kbs_after_reply_content_section', $reply ); ?>
        </div>
    </div>

    <?php

    return ob_get_clean();

} // kbs_get_reply_html

/**
 * Retrieve the name of the person who replied to the ticket.
 *
 * @since	1.0
 * @param	obj|int	$reply		The reply object or ID
 * @param	bool	$role		Whether or not to include the role in the response
 * @return	str		The name of the person who authored the reply. If $role is true, their role in brackets
 */
function kbs_get_reply_author_name( $reply, $role = false )	{
	if ( is_numeric( $reply ) ) {
		$reply = get_post( $reply );
	}

	$author      = __( 'Unknown', 'kb-support' );
	$author_role = '';

	if ( ! empty( $reply->post_author ) ) {
		$author      = get_userdata( $reply->post_author );
		$author      = $author->display_name;

        if ( kbs_is_agent( $reply->post_author ))   {
            $author_role = __( 'Agent', 'kb-support' );
        } else  {
            $author_role = __( 'Customer', 'kb-support' );
        }
	} else {
		$customer_id = get_post_meta( $reply->ID, '_kbs_reply_customer_id', true );
		if ( $customer_id )	{
			$customer = new KBS_Customer( $customer_id );
			if ( $customer )	{
				$author      = $customer->name;
				$author_role = __( 'Customer', 'kb-support' );
			}
		}
	}

	if ( $role && ! empty( $author_role ) )	{
		$author .= ' (' . $author_role . ')';
	}

	return apply_filters( 'kbs_reply_author_name', $author, $reply, $role, $author_role );

} // kbs_get_reply_author_name

/**
 * Retrieve ticket ID from reply.
 *
 * @since   1.2
 * @param   int     $reply_id
 * @return  int|false
 */
function kbs_get_ticket_id_from_reply( $reply_id )  {
    $ticket_id = get_post_field( 'post_parent', $reply_id );
    return apply_filters( 'kbs_ticket_id_from_reply', $ticket_id );
} // kbs_get_ticket_id_from_reply

/**
 * Mark a reply as read.
 *
 * @since   1.2
 * @param   int     $reply_id
 * @return  int|false
 */
function kbs_mark_reply_as_read( $reply_id )  {

    $ticket_id = kbs_get_ticket_id_from_reply( $reply_id );

    if ( empty( $ticket_id) )   {
        return false;
    }

    $ticket      = new KBS_Ticket( $ticket_id );
    $customer_id = $ticket->customer_id;

    if ( ! empty( $customer_id ) )  {
        $user_id = get_current_user_id();
        if ( ! empty( $user_id ) )  {
            if ( $user_id !== $customer_id )    {
                $mark_read = false;
            }
        }
    }

    $mark_read = apply_filters( 'kbs_mark_reply_as_read', true, $reply_id, $ticket );

    if ( ! $mark_read ) {
        return false;
    }

    do_action( 'kbs_customer_read_reply', $reply_id, $ticket );

    return add_post_meta( $reply_id, '_kbs_reply_customer_read', current_time( 'mysql'), true );

} // kbs_mark_reply_as_read

/**
 * Whether or not a reply has been read.
 *
 * @since   1.2
 * @param   int         $reply_id
 * @return  str|false   false if unread, otherwise the datetime the reply was read.
 */
function kbs_reply_is_read( $reply_id ) {
    $read = get_post_meta( $reply_id, '_kbs_reply_customer_read', true );

    return apply_filters( 'kbs_reply_is_read', $read, $reply_id );
} // kbs_reply_is_read
