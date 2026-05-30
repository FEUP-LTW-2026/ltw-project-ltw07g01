<?php
  class Session {
    private array $messages;

    public function __construct() {
      session_start();

      $this->messages = isset($_SESSION['messages']) ? $_SESSION['messages'] : array();
      unset($_SESSION['messages']);
    }

    public function getCsrfToken(): string {
      if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      }
      return $_SESSION['csrf_token'];
    }

    public function validateCsrfToken(string $token): bool {
      return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public function isLoggedIn() : bool {
      return isset($_SESSION['id']);    
    }

    public function logout() {
      $_SESSION = [];
      session_destroy();
    }

    public function getId() : ?int {
      return isset($_SESSION['id']) ? $_SESSION['id'] : null;    
    }

    public function getName() : ?string {
      return isset($_SESSION['name']) ? $_SESSION['name'] : null;
    }

    public function setId(int $id) {
      $_SESSION['id'] = $id;
    }

    public function setName(string $name) {
      $_SESSION['name'] = $name;
    }

    public function addMessage(string $type, string $text) {
      $_SESSION['messages'][] = array('type' => $type, 'text' => $text);
    }

    public function getMessages() {
      return $this->messages;
    }
  }
?>
<?php
function csrf_field(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}
?>