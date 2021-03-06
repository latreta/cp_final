<?php

namespace App\Http\Controllers;

use App\Resposta;
use App\User;
use App\Store;
use App\Product;
use App\Produtos;
use App\Session;
use App\Activation;
use App\FileHandler;
use App\Order;
use App\Ban;
use App\Cart;

use App\Mail\AccountCreated;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;

use DB;

class SessionController extends Controller
{

  public function email_testing(){
    $pedido = array(
      'id' => 1,
      'produtos' => array(
        0 => array(
          "nome" => "Produto 1", "quantidade" => 5
        ),
        1 => array(
          "nome" => "Produto 2", "quantidade" => 6
        )
      )
        );
    Mail::to("yves_henry13@hotmail.com")->send(new OrderCreated("123456", "Yves", $pedido));
  }

  // Realiza o login e seta o id na sessão
  public function login(){
    $data = $this->get_post();

    if(!Activation::isUserActivated($data['email'])){
      $this->return->setFailed("Esta conta não foi ativada, por favor visite seu email e ative-a agora.");
      return;
    }

    $session = Session::login($data['email'], $data['password']);

    if(Ban::verify($session)){
      $this->return->setFailed("Você está bloqueado de acessar o nosso sistema. Para mais informações entre em contato com o suporte.");
      return;
    }
    else{
      if($session != null){
        $_SESSION['user_id'] = $session;
        // Verifica loja
        $store_id = Store::getStoreID($_SESSION['user_id']);
        Cart::clearMyStore($store_id);
      }else{
        $this->return->setFailed("Login incorreto, tente novamente.");
      }
    }

  }
  // Realiza o logout acabando com a sessão(usuario e carrinho)
  public function logout(){
    if(isset($_SESSION['user_id'])){
      Session::logout();
    }else{
      $this->return->setFailed("Sessão expirada ou inexistente.");
    }
  }
  // Retorna TRUE para sessão ativa, e FALSE para sessão inexistente
  public function checkSession(){
    if(!Session::checkSession()){
      $this->return->setFailed("Sessão expirada ou inexistente.");
    }
  }
  // Pega informações do usuário logado
  public function get_infos(){
    $this->isLogged();
    $usuario = User::getLoggedUser($_SESSION['user_id']);
    
    if($usuario == null){
      $this->return->setFailed("Ocorreu um erro ao receber dados do usuario.");
      return;
    }
    else{
      $this->return->setObject($usuario);
      return;
    }
  }
  // Checa o status se a loja foi criada
  public function checkStore(){
    $this->isLogged();

    $loja = Store::storeExists($_SESSION['user_id']);

    if($loja == null){
			$this->return->setFailed("O usuário não possui loja cadastrada.");
			return;
		}
  }
  // Pega a loja do usuario logado
	public function logged_store(){
		$this->isLogged();

		$loja = Store::getLoggedStoreInfo($_SESSION['user_id']);

		if($loja == null){
			$this->return->setFailed("O usuário não possui loja cadastrada.");
			return;
		}else{
      $status = FileHandler::verifyStoreImage($loja->profile_image);
      if(!$status){
        $loja->profile_image = "default_profile1.png";
      }
      $this->return->setObject($loja);
    }
	}
  // Pega os produtos da loja do usuário logado
  public function logged_products(){
    $this->isLogged();
    $data = $this->get_post();

    if(!isset($data['page']) || strlen($data['page']) <= 0){
      $data['page'] = 0;
    }
    
    $produtos = Product::getLoggedStoreProducts($_SESSION['user_id'], $data['page']);

    if($produtos == null){
      $this->return->setFailed("Esta loja não possui nenhum produto.");
      return;
    }else{
      $this->return->setObject($produtos);
    }
  }
  public function isStoreCreated(){
    $this->isLogged();

    $status = Store::storeExists($_SESSION['user_id']);

    if(!$status){
      $this->return->setFailed("O usuário não possui loja.");
      return;
    }
  }
  // Status da loja
  public function status_store(){
    $this->isLogged();
    $status = Store::statusStore($_SESSION['user_id']);

    if($status){
      return;
    }
    else{
      $this->return->setFailed("Loja não criada ou desativada.");
      return;
    }
	}

}
