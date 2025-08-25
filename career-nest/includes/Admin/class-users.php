<?php
namespace CareerNest\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Users {
    public function hooks(): void {
        add_action( 'show_user_profile', [ $this, 'render_employer_field' ] );
        add_action( 'edit_user_profile', [ $this, 'render_employer_field' ] );
        add_action( 'user_new_form', [ $this, 'render_employer_field_on_new' ] );

        add_action( 'personal_options_update', [ $this, 'save_employer_field' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_employer_field' ] );
        add_action( 'user_register', [ $this, 'save_employer_field_on_create' ] );
    }

    private function get_employers_for_dropdown(): array {
        $ids = get_posts( [
            'post_type'      => 'employer',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'post_status'    => [ 'publish', 'private', 'draft', 'pending' ],
        ] );
        $out = [];
        foreach ( $ids as $id ) {
            $out[ (int) $id ] = get_the_title( $id );
        }
        return $out;
    }

    public function render_employer_field( \WP_User $user ): void {
        if ( ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }
        $is_team  = in_array( 'employer_team', (array) $user->roles, true );
        $employers = $this->get_employers_for_dropdown();
        $current   = (int) get_user_meta( $user->ID, '_employer_id', true );
        wp_nonce_field( 'careernest_user_employer_meta', 'careernest_user_employer_meta_nonce' );
        echo '<div id="careernest-employer-link-wrap-edit" style="' . ( $is_team ? '' : 'display:none;' ) . '">';
        echo '<h2>' . esc_html__( 'CareerNest — Employer Link', 'careernest' ) . '</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th><label for="careernest_employer_id">' . esc_html__( 'Related Employer', 'careernest' ) . '</label></th><td>';
        echo '<select id="careernest_employer_id" name="careernest_employer_id">';
        echo '<option value="">' . esc_html__( '— None —', 'careernest' ) . '</option>';
        foreach ( $employers as $id => $label ) {
            echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( $current, $id, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Shown only for Employer Team Members. Select the Employer they belong to.', 'careernest' ) . '</p>';
        echo '</td></tr>';
        echo '</table>';
        echo '</div>';
        // Toggle visibility when role is changed on the edit screen.
        echo '<script>(function(){
          var wrap = document.getElementById("careernest-employer-link-wrap-edit");
          var role = document.getElementById("role") || document.querySelector("select[name=role]");
          function update(){ if(!role||!wrap) return; wrap.style.display = (role.value === "employer_team") ? "block" : "none"; }
          if(role){ role.addEventListener("change", update); }
        })();</script>';
    }

    public function render_employer_field_on_new( string $operation ): void {
        // Only show on create-new user form in admin.
        if ( 'add-new-user' !== $operation ) {
            return;
        }
        $employers = $this->get_employers_for_dropdown();
        echo '<div id="careernest-employer-link-wrap" style="display:none">';
        echo '<h2>' . esc_html__( 'CareerNest — Employer Link', 'careernest' ) . '</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th><label for="careernest_employer_id">' . esc_html__( 'Related Employer', 'careernest' ) . '</label></th><td>';
        echo '<select id="careernest_employer_id" name="careernest_employer_id">';
        echo '<option value="">' . esc_html__( '— None —', 'careernest' ) . '</option>';
        foreach ( $employers as $id => $label ) {
            echo '<option value="' . esc_attr( (string) $id ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Shown only for Employer Team Members. Select the Employer they belong to.', 'careernest' ) . '</p>';
        echo '</td></tr>';
        echo '</table>';
        echo '</div>';
        // Inline JS to toggle visibility based on selected role
        echo '<script>(function(){
          var wrap = document.getElementById("careernest-employer-link-wrap");
          var role = document.getElementById("role") || document.querySelector("select[name=role]");
          function update(){ if(!role||!wrap) return; wrap.style.display = (role.value === "employer_team") ? "block" : "none"; }
          if(role){ role.addEventListener("change", update); update(); }
        })();</script>';
    }

    public function save_employer_field( int $user_id ): void {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }
        if ( ! isset( $_POST['careernest_user_employer_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['careernest_user_employer_meta_nonce'] ) ), 'careernest_user_employer_meta' ) ) {
            return;
        }
        $user = get_user_by( 'id', $user_id );
        if ( $user && in_array( 'employer_team', (array) $user->roles, true ) ) {
            $this->persist_user_employer_meta( $user_id );
        }
    }

    public function save_employer_field_on_create( int $user_id ): void {
        // Only persist for Employer Team Members.
        $user = get_user_by( 'id', $user_id );
        if ( ! $user || ! in_array( 'employer_team', (array) $user->roles, true ) ) {
            return;
        }
        if ( isset( $_POST['careernest_employer_id'] ) ) {
            $this->persist_user_employer_meta( $user_id );
        }
    }

    private function persist_user_employer_meta( int $user_id ): void {
        $employer_id = isset( $_POST['careernest_employer_id'] ) ? absint( $_POST['careernest_employer_id'] ) : 0;
        if ( $employer_id > 0 ) {
            $post = get_post( $employer_id );
            if ( $post && $post->post_type === 'employer' ) {
                update_user_meta( $user_id, '_employer_id', $employer_id );
                return;
            }
        }
        // Clear if invalid/empty
        delete_user_meta( $user_id, '_employer_id' );
    }
}
