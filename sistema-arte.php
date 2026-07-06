<?php
/**
 * Plugin Name: Sistema Arte
 * Description: Sistema de gerenciamento de demandas de arte com formulario publico, lista de pendencias e quadro Kanban no WordPress.
 * Version: 1.1.0
 * Author: Marco Antonio Vivas
 * Text Domain: sistema-arte
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sistema_Arte_Plugin {
	const POST_TYPE           = 'arte_demanda';
	const STATUS_TAXONOMY     = 'arte_status';
	const META_REQUESTER      = '_arte_requester_name';
	const META_SECRETARIAT    = '_arte_secretariat';
	const META_LOCATION       = '_arte_location';
	const META_CONTACT        = '_arte_contact';
	const META_DETAILS        = '_arte_details';
	const META_DUE_DATE       = '_arte_due_date';
	const META_PRIORITY       = '_arte_priority';
	const META_SEQUENCE       = '_arte_sequence_id';
	const META_ATTACHMENT_ID  = '_arte_attachment_id';
	const META_FINAL_ART_ID   = '_arte_final_art_id';
	const OPTION_LAST_ID      = 'arte_last_sequence_id';
	const OPTION_LOCATIONS    = 'arte_locations';
	const AJAX_NONCE_ACTION   = 'arte_kanban_nonce';
	const FORM_NONCE_ACTION   = 'arte_front_form_nonce';
	const STATUS_BACKLOG      = 'demanda';
	const STATUS_TODO         = 'fazer';
	const STATUS_DOING        = 'fazendo';
	const STATUS_DONE         = 'feito';
	const STATUS_ARCHIVED     = 'arquivada';

	/**
	 * Boot plugin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_status_taxonomy' ) );
		add_action( 'init', array( $this, 'maybe_handle_front_submission' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'post_edit_form_tag', array( $this, 'add_edit_form_enctype' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'ensure_sequence_id' ), 10, 3 );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'ensure_default_status' ), 20, 3 );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_final_art_meta' ), 30, 3 );
		add_action( 'wp_ajax_arte_update_status', array( $this, 'ajax_update_status' ) );
		add_action( 'admin_post_arte_archive_demanda', array( $this, 'handle_archive_request' ) );
		add_action( 'admin_post_arte_restore_demanda', array( $this, 'handle_restore_request' ) );
		add_action( 'admin_post_arte_save_locations', array( $this, 'handle_locations_save' ) );
		add_action( 'admin_post_arte_factory_reset', array( $this, 'handle_factory_reset' ) );
		add_filter( 'upload_mimes', array( $this, 'allow_common_upload_types' ) );

		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
	}

	/**
	 * Plugin activation handler.
	 *
	 * @return void
	 */
	public static function activate() {
		$plugin = new self();
		$plugin->register_post_type();
		$plugin->register_status_taxonomy();
		$plugin->create_default_terms();
		$plugin->maybe_seed_locations();
		flush_rewrite_rules();
	}

	/**
	 * Register custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => 'Demandas de Arte',
					'singular_name' => 'Demanda de Arte',
					'add_new_item'  => 'Nova Demanda de Arte',
					'edit_item'     => 'Editar Demanda',
					'menu_name'     => 'Demandas de Arte',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'supports'        => array( 'title', 'editor', 'author', 'thumbnail' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'capability_type' => 'post',
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-art',
			)
		);
	}

	/**
	 * Register taxonomy for workflow statuses.
	 *
	 * @return void
	 */
	public function register_status_taxonomy() {
		register_taxonomy(
			self::STATUS_TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => 'Status',
					'singular_name' => 'Status',
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'rewrite'           => false,
			)
		);

		$this->create_default_terms();
	}

	/**
	 * Create workflow terms when missing.
	 *
	 * @return void
	 */
	private function create_default_terms() {
		$terms = array(
			self::STATUS_BACKLOG  => 'Demanda',
			self::STATUS_TODO     => 'Fazer',
			self::STATUS_DOING    => 'Fazendo',
			self::STATUS_DONE     => 'Feito',
			self::STATUS_ARCHIVED => 'Arquivada',
		);

		foreach ( $terms as $slug => $label ) {
			if ( ! term_exists( $slug, self::STATUS_TAXONOMY ) ) {
				wp_insert_term(
					$label,
					self::STATUS_TAXONOMY,
					array(
						'slug' => $slug,
					)
				);
			}
		}
	}

	/**
	 * Seed default locations on first run.
	 *
	 * @return void
	 */
	private function maybe_seed_locations() {
		$stored = get_option( self::OPTION_LOCATIONS, null );

		if ( null !== $stored ) {
			return;
		}

		update_option(
			self::OPTION_LOCATIONS,
			array(
				'Gabinete',
				'Saude',
				'Educacao',
				'Assistencia Social',
				'Comunicacao',
			),
			false
		);
	}

	/**
	 * Register shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode() {
		add_shortcode( 'Sistema-Arte', array( $this, 'render_shortcode' ) );
		add_shortcode( 'sistema-arte', array( $this, 'render_shortcode' ) );
		add_shortcode( 'Sistema-Arte-Acompanhar', array( $this, 'render_tracking_shortcode' ) );
		add_shortcode( 'sistema-arte-acompanhar', array( $this, 'render_tracking_shortcode' ) );
	}

	/**
	 * Register public assets.
	 *
	 * @return void
	 */
	public function enqueue_public_assets() {
		wp_register_style(
			'sistema-arte-public',
			plugin_dir_url( __FILE__ ) . 'assets/css/public.css',
			array(),
			'1.1.0'
		);

		wp_register_script(
			'sistema-arte-public',
			plugin_dir_url( __FILE__ ) . 'assets/js/public.js',
			array(),
			'1.1.0',
			true
		);
	}

	/**
	 * Enqueue admin assets on plugin pages.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		$allowed_hooks = array(
			'toplevel_page_sistema-arte-kanban',
			'sistema-arte_page_sistema-arte-arquivadas',
			'sistema-arte_page_sistema-arte-locais',
			'sistema-arte_page_sistema-arte-ferramentas',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'sistema-arte-admin',
			plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
			array(),
			'1.1.0'
		);

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'sistema-arte-admin',
			plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			'1.1.0',
			true
		);

		wp_localize_script(
			'sistema-arte-admin',
			'arteKanban',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_NONCE_ACTION ),
			)
		);
	}

	/**
	 * Register top-level menu and submenus.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_menu_page(
			'Sistema Arte',
			'Sistema Arte',
			'edit_posts',
			'sistema-arte-kanban',
			array( $this, 'render_admin_page' ),
			'dashicons-format-gallery',
			26
		);

		add_submenu_page(
			'sistema-arte-kanban',
			'Sistema Arte',
			'Kanban',
			'edit_posts',
			'sistema-arte-kanban',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			'sistema-arte-kanban',
			'Demandas Arquivadas',
			'Demandas Arquivadas',
			'edit_posts',
			'sistema-arte-arquivadas',
			array( $this, 'render_archived_page' )
		);

		add_submenu_page(
			'sistema-arte-kanban',
			'Locais',
			'Locais',
			'manage_options',
			'sistema-arte-locais',
			array( $this, 'render_locations_page' )
		);

		add_submenu_page(
			'sistema-arte-kanban',
			'Ferramentas',
			'Ferramentas',
			'manage_options',
			'sistema-arte-ferramentas',
			array( $this, 'render_tools_page' )
		);
	}

	/**
	 * Register admin meta boxes for demand editing.
	 *
	 * @return void
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'arte-final-art',
			'Arte Pronta',
			array( $this, 'render_final_art_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Ensure the demand edit form can upload files.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function add_edit_form_enctype( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( self::POST_TYPE !== $post->post_type ) {
			return;
		}

		echo ' enctype="multipart/form-data"';
	}

	/**
	 * Ensure a sequential code exists.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Update flag.
	 * @return void
	 */
	public function ensure_sequence_id( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( get_post_meta( $post_id, self::META_SEQUENCE, true ) ) {
			return;
		}

		$last_id   = (int) get_option( self::OPTION_LAST_ID, 0 );
		$next_id   = $last_id + 1;
		$formatted = sprintf( 'A%03d', $next_id );

		update_post_meta( $post_id, self::META_SEQUENCE, $formatted );
		update_option( self::OPTION_LAST_ID, $next_id, false );
	}

	/**
	 * Ensure default workflow status exists on save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Update flag.
	 * @return void
	 */
	public function ensure_default_status( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		$terms = wp_get_post_terms( $post_id, self::STATUS_TAXONOMY, array( 'fields' => 'ids' ) );

		if ( empty( $terms ) ) {
			wp_set_object_terms( $post_id, self::STATUS_BACKLOG, self::STATUS_TAXONOMY, false );
		}
	}

	/**
	 * Handle public form submission.
	 *
	 * @return void
	 */
	public function maybe_handle_front_submission() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		if ( empty( $_POST['arte_front_submit'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			$this->redirect_with_notice( 'erro=login_obrigatorio' );
		}

		$nonce = isset( $_POST['arte_front_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['arte_front_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::FORM_NONCE_ACTION ) ) {
			return;
		}

		$title     = sanitize_text_field( wp_unslash( $_POST['arte_title'] ?? '' ) );
		$requester = sanitize_text_field( wp_unslash( $_POST['arte_requester'] ?? '' ) );
		$location  = sanitize_text_field( wp_unslash( $_POST['arte_location'] ?? '' ) );
		$contact   = sanitize_text_field( wp_unslash( $_POST['arte_contact'] ?? '' ) );
		$details   = sanitize_textarea_field( wp_unslash( $_POST['arte_details'] ?? '' ) );
		$due_date  = sanitize_text_field( wp_unslash( $_POST['arte_due_date'] ?? '' ) );
		$priority  = sanitize_text_field( wp_unslash( $_POST['arte_priority'] ?? '' ) );

		if ( empty( $due_date ) ) {
			$due_date = $this->get_default_due_date_value();
		}

		$required = array( $title, $requester, $location, $contact, $details, $due_date, $priority );

		foreach ( $required as $field ) {
			if ( empty( $field ) ) {
				$this->redirect_with_notice( 'erro=campos_obrigatorios' );
			}
		}

		$author_id = get_current_user_id();

		if ( current_user_can( 'edit_others_posts' ) && ! empty( $_POST['arte_author'] ) ) {
			$selected_author = (int) $_POST['arte_author'];
			if ( $selected_author > 0 && get_userdata( $selected_author ) ) {
				$author_id = $selected_author;
			}
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $details,
				'post_author'  => $author_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->redirect_with_notice( 'erro=falha_ao_salvar' );
		}

		update_post_meta( $post_id, self::META_REQUESTER, $requester );
		update_post_meta( $post_id, self::META_LOCATION, $location );
		update_post_meta( $post_id, self::META_SECRETARIAT, $location );
		update_post_meta( $post_id, self::META_CONTACT, $contact );
		update_post_meta( $post_id, self::META_DETAILS, $details );
		update_post_meta( $post_id, self::META_DUE_DATE, $this->normalize_due_date( $due_date ) );
		update_post_meta( $post_id, self::META_PRIORITY, $priority );

		if ( ! empty( $_FILES['arte_attachment']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$attachment_id = media_handle_upload( 'arte_attachment', $post_id );

			if ( ! is_wp_error( $attachment_id ) ) {
				update_post_meta( $post_id, self::META_ATTACHMENT_ID, $attachment_id );

				if ( wp_attachment_is_image( $attachment_id ) ) {
					set_post_thumbnail( $post_id, $attachment_id );
				}
			}
		}

		wp_set_object_terms( $post_id, self::STATUS_BACKLOG, self::STATUS_TAXONOMY, false );
		$this->redirect_with_notice( 'sucesso=1' );
	}

	/**
	 * Render public shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required_notice();
		}

		wp_enqueue_style( 'sistema-arte-public' );
		wp_enqueue_script( 'sistema-arte-public' );

		$requester_value = $this->get_default_requester_name();

		ob_start();
		?>
		<div class="arte-board">
			<div class="arte-grid">
				<section class="arte-card arte-form-card">
					<h2>Adicionar uma nova demanda</h2>
					<?php $this->render_form_notice(); ?>
					<form method="post" enctype="multipart/form-data" class="arte-form">
						<?php wp_nonce_field( self::FORM_NONCE_ACTION, 'arte_front_nonce' ); ?>
						<label>
							<span>Titulo da Arte*</span>
							<input type="text" name="arte_title" required>
						</label>
						<div class="arte-two-columns">
							<label>
								<span>Seu nome completo *</span>
								<input type="text" name="arte_requester" value="<?php echo esc_attr( $requester_value ); ?>" required>
							</label>
							<label>
								<span>Local *</span>
								<select name="arte_location" required>
									<option value="">Selecione...</option>
									<?php foreach ( $this->get_locations() as $location ) : ?>
										<option value="<?php echo esc_attr( $location ); ?>"><?php echo esc_html( $location ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</div>
						<?php if ( current_user_can( 'edit_others_posts' ) ) : ?>
							<label>
								<span>Atribuir a (autor)</span>
								<?php
								wp_dropdown_users(
									array(
										'name'             => 'arte_author',
										'id'               => 'arte_author',
										'show_option_none' => 'Selecionar autor...',
										'option_none_value' => '',
										'orderby'          => 'display_name',
										'order'            => 'ASC',
									)
								);
								?>
								<small class="arte-help-text">Atribua esta demanda a um usuario especifico.</small>
							</label>
						<?php endif; ?>
						<label>
							<span>Telefone/WhatsApp *</span>
							<input type="text" name="arte_contact" class="arte-phone-input" placeholder="(99) 99999-9999" inputmode="numeric" maxlength="15" required>
						</label>
						<label>
							<span>Detalhes da Solicitacao *</span>
							<textarea name="arte_details" rows="6" required></textarea>
						</label>
						<label>
							<span>Anexar Arquivos (opcional)</span>
							<input type="file" name="arte_attachment" accept=".pdf,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.ppt,.pptx,.zip,.rar">
							<small class="arte-help-text">Formatos aceitos: PDF, XLS, XLSX, CSV, TXT, PNG, JPG, GIF, WEBP, DOC, DOCX, PPT, PPTX, ZIP e RAR.</small>
						</label>
						<div class="arte-two-columns">
							<label>
								<span>Data de Entrega *</span>
								<input type="datetime-local" name="arte_due_date" value="<?php echo esc_attr( $this->get_default_due_date_value() ); ?>" required>
							</label>
							<label>
								<span>Prioridade</span>
								<select name="arte_priority" required>
									<option value="baixa">Baixa</option>
									<option value="media" selected>Media</option>
									<option value="alta">Alta</option>
								</select>
							</label>
						</div>
						<button type="submit" name="arte_front_submit" value="1">Enviar Solicitacao</button>
					</form>
				</section>

				<section class="arte-card arte-list-card">
					<h2>Demandas Pendentes</h2>
					<?php $this->render_pending_table(); ?>
				</section>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render tracking shortcode for the logged-in requester.
	 *
	 * @return string
	 */
	public function render_tracking_shortcode() {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required_notice();
		}

		wp_enqueue_style( 'sistema-arte-public' );

		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'author'         => get_current_user_id(),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		ob_start();
		?>
		<div class="arte-board">
			<div class="arte-card arte-tracking-card">
				<h2>Acompanhar Demandas</h2>
				<?php if ( ! $query->have_posts() ) : ?>
					<p class="arte-empty-state">Voce ainda nao possui demandas cadastradas.</p>
				<?php else : ?>
					<table class="arte-pending-table arte-tracking-table">
						<thead>
							<tr>
								<th>ID</th>
								<th>Titulo</th>
								<th>Status</th>
								<th>Entrega</th>
								<th>Arte Pronta</th>
							</tr>
						</thead>
						<tbody>
							<?php while ( $query->have_posts() ) : ?>
								<?php
								$query->the_post();
								$post_id      = get_the_ID();
								$sequence     = get_post_meta( $post_id, self::META_SEQUENCE, true );
								$due_date     = get_post_meta( $post_id, self::META_DUE_DATE, true );
								$final_art_id = (int) get_post_meta( $post_id, self::META_FINAL_ART_ID, true );
								?>
								<tr>
									<td><?php echo esc_html( $sequence ); ?></td>
									<td><?php echo esc_html( get_the_title() ); ?></td>
									<td><span class="arte-status-pill arte-status-<?php echo esc_attr( $this->get_status_slug( $post_id ) ); ?>"><?php echo esc_html( $this->get_status_label( $post_id ) ); ?></span></td>
									<td><?php echo esc_html( $this->format_due_date( $due_date ) ); ?></td>
									<td class="arte-final-art-cell">
										<?php if ( $final_art_id ) :
											$art_url = wp_get_attachment_url( $final_art_id );
											$art_file = get_attached_file( $final_art_id );
											$art_filename = $art_file ? basename( $art_file ) : basename( $art_url );
										?>
											<a href="<?php echo esc_url( $art_url ); ?>" target="_blank" rel="noopener noreferrer" class="arte-btn-visualizar">Visualizar arte</a>
											<a href="<?php echo esc_url( $art_url ); ?>" download="<?php echo esc_attr( $art_filename ); ?>" class="arte-btn-baixar">Baixar arte</a>
										<?php else : ?>
											<span class="arte-muted-text">Aguardando</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php

		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/**
	 * Render public notices.
	 *
	 * @return void
	 */
	private function render_form_notice() {
		if ( empty( $_GET['arte_status'] ) ) {
			return;
		}

		$status = sanitize_text_field( wp_unslash( $_GET['arte_status'] ) );

		if ( 'sucesso' === $status ) {
			echo '<div class="arte-notice arte-notice-success">Solicitacao enviada com sucesso.</div>';
			return;
		}

		if ( 'campos_obrigatorios' === $status ) {
			echo '<div class="arte-notice arte-notice-error">Preencha todos os campos obrigatorios antes de enviar.</div>';
			return;
		}

		if ( 'login_obrigatorio' === $status ) {
			echo '<div class="arte-notice arte-notice-error">Voce precisa estar logado para enviar uma solicitacao.</div>';
			return;
		}

		if ( 'falha_ao_salvar' === $status ) {
			echo '<div class="arte-notice arte-notice-error">Nao foi possivel salvar a solicitacao. Tente novamente.</div>';
		}
	}

	/**
	 * Render message for non-authenticated visitors.
	 *
	 * @return string
	 */
	private function render_login_required_notice() {
		wp_enqueue_style( 'sistema-arte-public' );

		$current_url = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) );
		$login_url   = wp_login_url( $current_url );

		ob_start();
		?>
		<div class="arte-board">
			<div class="arte-card arte-login-card">
				<h2>Acesso restrito</h2>
				<p>Voce precisa estar logado no WordPress para abrir e enviar demandas de arte.</p>
				<p><a class="arte-login-link" href="<?php echo esc_url( $login_url ); ?>">Fazer login</a></p>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render pending table.
	 *
	 * @return void
	 */
	private function render_pending_table() {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_key'       => self::META_DUE_DATE,
				'tax_query'      => array(
					array(
						'taxonomy' => self::STATUS_TAXONOMY,
						'field'    => 'slug',
						'terms'    => array( self::STATUS_DONE, self::STATUS_ARCHIVED ),
						'operator' => 'NOT IN',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			echo '<p class="arte-empty-state">Nenhuma demanda pendente no momento.</p>';
			return;
		}

		?>
		<table class="arte-pending-table">
			<thead>
				<tr>
					<th>ID</th>
					<th>Titulo</th>
					<th>Vencimento</th>
					<th>Prioridade</th>
				</tr>
			</thead>
			<tbody>
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$post_id  = get_the_ID();
					$sequence = get_post_meta( $post_id, self::META_SEQUENCE, true );
					$due_date = get_post_meta( $post_id, self::META_DUE_DATE, true );
					$priority = get_post_meta( $post_id, self::META_PRIORITY, true );
					?>
					<tr>
						<td><?php echo esc_html( $sequence ); ?></td>
						<td><?php echo esc_html( get_the_title() ); ?></td>
						<td><?php echo esc_html( $this->format_due_date( $due_date ) ); ?></td>
						<td><span class="arte-priority arte-priority-<?php echo esc_attr( $priority ); ?>"><?php echo esc_html( ucfirst( $priority ) ); ?></span></td>
					</tr>
				<?php endwhile; ?>
			</tbody>
		</table>
		<?php

		wp_reset_postdata();
	}

	/**
	 * Render Kanban page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		$columns = array(
			self::STATUS_BACKLOG => 'Demanda',
			self::STATUS_TODO    => 'Fazer',
			self::STATUS_DOING   => 'Fazendo',
			self::STATUS_DONE    => 'Feito',
		);
		?>
		<div class="wrap arte-admin-wrap">
			<h1>Sistema Arte</h1>
			<?php $this->render_admin_notice(); ?>
			<p>Gerencie o fluxo das demandas arrastando os cards entre as colunas.</p>
			<div class="arte-kanban">
				<?php foreach ( $columns as $slug => $label ) : ?>
					<section class="arte-kanban-column" data-status="<?php echo esc_attr( $slug ); ?>">
						<header>
							<h2><?php echo esc_html( $label ); ?></h2>
							<span><?php echo esc_html( $this->count_posts_by_status( $slug ) ); ?></span>
						</header>
						<div class="arte-kanban-list">
							<?php foreach ( $this->get_posts_by_status( $slug ) as $post ) : ?>
								<?php $this->render_kanban_card( $post ); ?>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render archived page.
	 *
	 * @return void
	 */
	public function render_archived_page() {
		$posts = $this->get_posts_by_status( self::STATUS_ARCHIVED );
		?>
		<div class="wrap arte-admin-wrap">
			<h1>Demandas Arquivadas</h1>
			<?php $this->render_admin_notice(); ?>
			<?php if ( empty( $posts ) ) : ?>
				<p>Nenhuma demanda arquivada no momento.</p>
			<?php else : ?>
				<table class="widefat striped arte-archive-table">
					<thead>
						<tr>
							<th>ID</th>
							<th>Titulo</th>
							<th>Local</th>
							<th>Entrega</th>
							<th>Prioridade</th>
							<th>Acoes</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $posts as $post ) : ?>
							<?php
							$location = get_post_meta( $post->ID, self::META_LOCATION, true );
							if ( empty( $location ) ) {
								$location = get_post_meta( $post->ID, self::META_SECRETARIAT, true );
							}
							$priority = get_post_meta( $post->ID, self::META_PRIORITY, true );
							$attachment_id = (int) get_post_meta( $post->ID, self::META_ATTACHMENT_ID, true );
							?>
							<tr>
								<td><?php echo esc_html( get_post_meta( $post->ID, self::META_SEQUENCE, true ) ); ?></td>
								<td><?php echo esc_html( get_the_title( $post ) ); ?></td>
								<td><?php echo esc_html( $location ); ?></td>
								<td><?php echo esc_html( $this->format_due_date( get_post_meta( $post->ID, self::META_DUE_DATE, true ) ) ); ?></td>
								<td><span class="arte-priority arte-priority-<?php echo esc_attr( $priority ); ?>"><?php echo esc_html( ucfirst( $priority ) ); ?></span></td>
								<td class="arte-table-actions">
									<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" class="button button-secondary">Abrir demanda</a>
									<?php if ( $attachment_id ) : ?>
										<a href="<?php echo esc_url( wp_get_attachment_url( $attachment_id ) ); ?>" class="button" target="_blank" rel="noopener noreferrer">Ver anexo</a>
									<?php endif; ?>
									<a href="<?php echo esc_url( $this->get_restore_url( $post->ID ) ); ?>" class="button button-primary">Restaurar</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render locations page.
	 *
	 * @return void
	 */
	public function render_locations_page() {
		?>
		<div class="wrap arte-admin-wrap">
			<h1>Locais</h1>
			<?php $this->render_admin_notice(); ?>
			<p>Cadastre um local por linha. Essa lista sera usada no formulario publico.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="arte-settings-form">
				<input type="hidden" name="action" value="arte_save_locations">
				<?php wp_nonce_field( 'arte_save_locations' ); ?>
				<textarea name="arte_locations" rows="12" class="large-text code"><?php echo esc_textarea( implode( "\n", $this->get_locations() ) ); ?></textarea>
				<p><button type="submit" class="button button-primary">Salvar locais</button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render maintenance tools page.
	 *
	 * @return void
	 */
	public function render_tools_page() {
		?>
		<div class="wrap arte-admin-wrap">
			<h1>Ferramentas</h1>
			<?php $this->render_admin_notice(); ?>
			<div class="arte-danger-box">
				<h2>Zerar demandas</h2>
				<p>Essa acao apaga permanentemente todas as demandas do plugin, incluindo anexos enviados com elas e artes prontas vinculadas.</p>
				<p>Tambem reinicia o contador sequencial e restaura a lista de locais para o padrao inicial.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="arte-settings-form arte-danger-form">
					<input type="hidden" name="action" value="arte_factory_reset">
					<?php wp_nonce_field( 'arte_factory_reset' ); ?>
					<p>
						<label>
							<input type="checkbox" name="arte_confirm_reset" value="1" required>
							Eu entendo que esta acao nao pode ser desfeita.
						</label>
					</p>
					<p>
						<label for="arte-reset-phrase"><strong>Digite <code>ZERAR DEMANDAS</code> para confirmar:</strong></label><br>
						<input type="text" id="arte-reset-phrase" name="arte_reset_phrase" class="regular-text" autocomplete="off" required>
					</p>
					<p>
						<button type="submit" class="button button-secondary arte-danger-button">Zerar demandas</button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render metabox for uploading the final art on the demand edit screen.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_final_art_meta_box( $post ) {
		$final_art_id = (int) get_post_meta( $post->ID, self::META_FINAL_ART_ID, true );

		wp_nonce_field( 'arte_save_final_art', 'arte_final_art_nonce' );
		?>
		<p>Envie aqui a arte final pronta para esta demanda.</p>
		<?php if ( $final_art_id ) : ?>
			<p>
				<strong>Arquivo atual:</strong><br>
				<a href="<?php echo esc_url( wp_get_attachment_url( $final_art_id ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( wp_basename( get_attached_file( $final_art_id ) ) ); ?>
				</a>
			</p>
			<p>
				<label>
					<input type="checkbox" name="arte_remove_final_art" value="1">
					Remover arte pronta atual
				</label>
			</p>
		<?php endif; ?>
		<p>
			<input type="file" name="arte_final_attachment" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.psd,.ai,.cdr,.zip,.rar">
		</p>
		<p class="description">Se enviar um novo arquivo, ele substituira a arte pronta atual.</p>
		<?php
	}

	/**
	 * Render one Kanban card.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	private function render_kanban_card( $post ) {
		$sequence  = get_post_meta( $post->ID, self::META_SEQUENCE, true );
		$priority  = get_post_meta( $post->ID, self::META_PRIORITY, true );
		$due_date  = get_post_meta( $post->ID, self::META_DUE_DATE, true );
		$requester = get_post_meta( $post->ID, self::META_REQUESTER, true );
		$location  = get_post_meta( $post->ID, self::META_LOCATION, true );
		$attachment_id = (int) get_post_meta( $post->ID, self::META_ATTACHMENT_ID, true );

		if ( empty( $location ) ) {
			$location = get_post_meta( $post->ID, self::META_SECRETARIAT, true );
		}
		?>
		<article class="arte-kanban-card" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
			<div class="arte-kanban-meta">
				<strong><?php echo esc_html( $sequence ); ?></strong>
				<span class="arte-priority arte-priority-<?php echo esc_attr( $priority ); ?>"><?php echo esc_html( ucfirst( $priority ) ); ?></span>
			</div>
			<h3><?php echo esc_html( get_the_title( $post ) ); ?></h3>
			<p><?php echo esc_html( wp_trim_words( $post->post_content, 18 ) ); ?></p>
			<ul>
				<li><strong>Solicitante:</strong> <?php echo esc_html( $requester ); ?></li>
				<li><strong>Local:</strong> <?php echo esc_html( $location ); ?></li>
				<li><strong>Entrega:</strong> <?php echo esc_html( $this->format_due_date( $due_date ) ); ?></li>
			</ul>
			<?php $this->render_attachment_link( $attachment_id ); ?>
			<div class="arte-kanban-actions">
				<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">Abrir demanda</a>
				<a href="<?php echo esc_url( $this->get_archive_url( $post->ID ) ); ?>" class="arte-archive-link">Arquivar demanda</a>
			</div>
		</article>
		<?php
	}

	/**
	 * Get posts by workflow status.
	 *
	 * @param string $status_slug Status term slug.
	 * @return WP_Post[]
	 */
	private function get_posts_by_status( $status_slug ) {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_key'       => self::META_DUE_DATE,
				'tax_query'      => array(
					array(
						'taxonomy' => self::STATUS_TAXONOMY,
						'field'    => 'slug',
						'terms'    => $status_slug,
					),
				),
			)
		);
	}

	/**
	 * Count posts in a workflow status.
	 *
	 * @param string $status_slug Status term slug.
	 * @return int
	 */
	private function count_posts_by_status( $status_slug ) {
		return count( $this->get_posts_by_status( $status_slug ) );
	}

	/**
	 * Update status from Kanban drag and drop.
	 *
	 * @return void
	 */
	public function ajax_update_status() {
		check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permissao negada.' ), 403 );
		}

		$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $post_id || ! in_array( $status, array( self::STATUS_BACKLOG, self::STATUS_TODO, self::STATUS_DOING, self::STATUS_DONE ), true ) ) {
			wp_send_json_error( array( 'message' => 'Dados invalidos.' ), 400 );
		}

		$result = wp_set_object_terms( $post_id, $status, self::STATUS_TAXONOMY, false );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => 'Falha ao atualizar o status.' ), 500 );
		}

		wp_send_json_success();
	}

	/**
	 * Save the final art attachment from the edit screen.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Update flag.
	 * @return void
	 */
	public function save_final_art_meta( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$has_nonce = isset( $_POST['arte_final_art_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['arte_final_art_nonce'] ) ), 'arte_save_final_art' );
		$has_file  = ! empty( $_FILES['arte_final_attachment']['name'] );
		$remove    = ! empty( $_POST['arte_remove_final_art'] );

		if ( ! $has_nonce && ! $has_file && ! $remove ) {
			return;
		}

		if ( ! $has_nonce ) {
			return;
		}

		if ( $remove ) {
			delete_post_meta( $post_id, self::META_FINAL_ART_ID );
		}

		if ( $has_file ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$attachment_id = media_handle_upload( 'arte_final_attachment', $post_id );

			if ( ! is_wp_error( $attachment_id ) ) {
				update_post_meta( $post_id, self::META_FINAL_ART_ID, $attachment_id );
			}
		}
	}

	/**
	 * Save admin-managed locations.
	 *
	 * @return void
	 */
	public function handle_locations_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permissao negada.' );
		}

		check_admin_referer( 'arte_save_locations' );

		$raw_locations = isset( $_POST['arte_locations'] ) ? wp_unslash( $_POST['arte_locations'] ) : '';
		$lines         = preg_split( '/\r\n|\r|\n/', (string) $raw_locations );
		$locations     = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $lines ) ) ) );

		update_option( self::OPTION_LOCATIONS, $locations, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'sistema-arte-locais',
					'arte_status' => 'locais_salvos',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Reset plugin data to the initial state.
	 *
	 * @return void
	 */
	public function handle_factory_reset() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permissao negada.' );
		}

		check_admin_referer( 'arte_factory_reset' );

		$confirmed = ! empty( $_POST['arte_confirm_reset'] );
		$phrase    = isset( $_POST['arte_reset_phrase'] ) ? sanitize_text_field( wp_unslash( $_POST['arte_reset_phrase'] ) ) : '';

		if ( ! $confirmed || 'ZERAR DEMANDAS' !== $phrase ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => 'sistema-arte-ferramentas',
						'arte_status' => 'reset_invalido',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$post_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $post_ids as $post_id ) {
			$attachment_ids = array(
				(int) get_post_meta( $post_id, self::META_ATTACHMENT_ID, true ),
				(int) get_post_meta( $post_id, self::META_FINAL_ART_ID, true ),
			);

			foreach ( array_unique( array_filter( $attachment_ids ) ) as $attachment_id ) {
				if ( get_post( $attachment_id ) ) {
					wp_delete_attachment( $attachment_id, true );
				}
			}

			wp_delete_post( $post_id, true );
		}

		delete_option( self::OPTION_LAST_ID );
		delete_option( self::OPTION_LOCATIONS );
		$this->maybe_seed_locations();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'sistema-arte-ferramentas',
					'arte_status' => 'reset_ok',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Archive a demand.
	 *
	 * @return void
	 */
	public function handle_archive_request() {
		$this->handle_status_action( 'arte_archive_demanda', self::STATUS_ARCHIVED, 'sistema-arte-kanban' );
	}

	/**
	 * Restore an archived demand.
	 *
	 * @return void
	 */
	public function handle_restore_request() {
		$this->handle_status_action( 'arte_restore_demanda', self::STATUS_BACKLOG, 'sistema-arte-arquivadas' );
	}

	/**
	 * Shared status action handler.
	 *
	 * @param string $action_nonce Nonce base.
	 * @param string $target_status Status slug.
	 * @param string $return_page Admin page slug.
	 * @return void
	 */
	private function handle_status_action( $action_nonce, $target_status, $return_page ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Permissao negada.' );
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		check_admin_referer( $action_nonce . '_' . $post_id );

		if ( $post_id ) {
			wp_set_object_terms( $post_id, $target_status, self::STATUS_TAXONOMY, false );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => $return_page,
					'arte_status' => 'ok',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Normalize datetime-local value to MySQL datetime.
	 *
	 * @param string $due_date Raw due date.
	 * @return string
	 */
	private function normalize_due_date( $due_date ) {
		if ( empty( $due_date ) ) {
			return '';
		}

		$datetime = \DateTime::createFromFormat( 'Y-m-d\TH:i', $due_date, wp_timezone() );

		if ( false === $datetime ) {
			return '';
		}

		return $datetime->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Default due date: today + 7 days at 17:00.
	 *
	 * @return string
	 */
	private function get_default_due_date_value() {
		$datetime = new \DateTimeImmutable( 'now', wp_timezone() );
		$datetime = $datetime->modify( '+7 days' )->setTime( 17, 0 );

		return $datetime->format( 'Y-m-d\TH:i' );
	}

	/**
	 * Format due date for display.
	 *
	 * @param string $due_date Stored value.
	 * @return string
	 */
	private function format_due_date( $due_date ) {
		if ( empty( $due_date ) ) {
			return 'Sem prazo';
		}

		$timestamp = strtotime( $due_date );

		if ( ! $timestamp ) {
			return $due_date;
		}

		return wp_date( 'd/m/Y H:i', $timestamp );
	}

	/**
	 * Get the status label for a demand.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_status_label( $post_id ) {
		$terms = wp_get_post_terms( $post_id, self::STATUS_TAXONOMY );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return 'Sem status';
		}

		return $terms[0]->name;
	}

	/**
	 * Get the status slug for a demand.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_status_slug( $post_id ) {
		$terms = wp_get_post_terms( $post_id, self::STATUS_TAXONOMY );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return 'sem-status';
		}

		return $terms[0]->slug;
	}

	/**
	 * Get stored locations.
	 *
	 * @return string[]
	 */
	private function get_locations() {
		$this->maybe_seed_locations();

		$locations = get_option( self::OPTION_LOCATIONS, array() );

		if ( ! is_array( $locations ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'sanitize_text_field', $locations )
			)
		);
	}

	/**
	 * Get default requester name for the public form.
	 *
	 * @return string
	 */
	private function get_default_requester_name() {
		if ( ! empty( $_POST['arte_requester'] ) ) {
			return sanitize_text_field( wp_unslash( $_POST['arte_requester'] ) );
		}

		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user = wp_get_current_user();

		if ( ! $user || 0 === (int) $user->ID ) {
			return '';
		}

		if ( ! empty( $user->display_name ) ) {
			return (string) $user->display_name;
		}

		if ( ! empty( $user->user_firstname ) || ! empty( $user->user_lastname ) ) {
			return trim( $user->user_firstname . ' ' . $user->user_lastname );
		}

		return (string) $user->user_login;
	}

	/**
	 * Render attachment link block when available.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	private function render_attachment_link( $attachment_id ) {
		if ( ! $attachment_id ) {
			return;
		}

		$url = wp_get_attachment_url( $attachment_id );

		if ( ! $url ) {
			return;
		}

		$filename = wp_basename( get_attached_file( $attachment_id ) );
		?>
		<div class="arte-attachment-box">
			<strong>Anexo:</strong>
			<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $filename ? $filename : 'Abrir arquivo' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Render admin notices.
	 *
	 * @return void
	 */
	private function render_admin_notice() {
		if ( empty( $_GET['arte_status'] ) ) {
			return;
		}

		$status = sanitize_text_field( wp_unslash( $_GET['arte_status'] ) );

		if ( 'locais_salvos' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>Lista de locais salva com sucesso.</p></div>';
			return;
		}

		if ( 'reset_ok' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>Plugin restaurado para o estado inicial com sucesso.</p></div>';
			return;
		}

		if ( 'reset_invalido' === $status ) {
			echo '<div class="notice notice-error is-dismissible"><p>Confirmacao invalida. Nenhuma demanda foi apagada.</p></div>';
			return;
		}

		if ( 'ok' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>Acao executada com sucesso.</p></div>';
		}
	}

	/**
	 * Build archive action URL.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_archive_url( $post_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'arte_archive_demanda',
					'post_id' => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			'arte_archive_demanda_' . $post_id
		);
	}

	/**
	 * Build restore action URL.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_restore_url( $post_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'arte_restore_demanda',
					'post_id' => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			'arte_restore_demanda_' . $post_id
		);
	}

	/**
	 * Expand accepted upload formats.
	 *
	 * @param array $mimes Existing MIME map.
	 * @return array
	 */
	public function allow_common_upload_types( $mimes ) {
		$mimes['txt']  = 'text/plain';
		$mimes['csv']  = 'text/csv';
		$mimes['xls']  = 'application/vnd.ms-excel';
		$mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
		$mimes['doc']  = 'application/msword';
		$mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
		$mimes['ppt']  = 'application/vnd.ms-powerpoint';
		$mimes['pptx'] = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
		$mimes['rar']  = 'application/vnd.rar';
		$mimes['psd']  = 'image/vnd.adobe.photoshop';
		$mimes['ai']   = 'application/postscript';
		$mimes['cdr']  = 'application/cdr';

		return $mimes;
	}

	/**
	 * Redirect back with a notice key.
	 *
	 * @param string $query_string Query fragment.
	 * @return void
	 */
	private function redirect_with_notice( $query_string ) {
		$url = wp_get_referer();

		if ( ! $url && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$url = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		if ( ! $url ) {
			$url = home_url( '/' );
		}

		$url = remove_query_arg( 'arte_status', $url );

		if ( false !== strpos( $query_string, 'sucesso=1' ) ) {
			$url = add_query_arg( 'arte_status', 'sucesso', $url );
		} elseif ( false !== strpos( $query_string, 'campos_obrigatorios' ) ) {
			$url = add_query_arg( 'arte_status', 'campos_obrigatorios', $url );
		} elseif ( false !== strpos( $query_string, 'login_obrigatorio' ) ) {
			$url = add_query_arg( 'arte_status', 'login_obrigatorio', $url );
		} else {
			$url = add_query_arg( 'arte_status', 'falha_ao_salvar', $url );
		}

		wp_safe_redirect( $url );
		exit;
	}
}

$GLOBALS['sistema_arte_plugin'] = new Sistema_Arte_Plugin();
$GLOBALS['sistema_arte_plugin']->init();
