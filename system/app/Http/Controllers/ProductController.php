<?php

namespace App\Http\Controllers;

use DB;
use App\Image;
use App\Store;
use App\Product;

use Illuminate\Support\Facades\Input;
use Validator;

class ProductController extends Controller
{

  private const NO_IMAGE = "Nenhuma imagem válida foi recebida.";
  // Criar produto
  public function newProduct(){
    $this->isLogged();
    
    if(Input::hasFile('imagem')){
      $image = Input::file('imagem');

      if($image == null || !$image->isValid()){
        $this->return->setFailed(self::NO_IMAGE);
        return;
      }

      $data = $_POST;

      if($data['shipping'] == true){
        $data['shipping'] = 1;
      }
  
      if($data['local'] == true){
        $data['local'] = 1;
      }
  
      $data['price'] = $this->transformPrice($data['price']);  
      $data['original_price'] = $this->transformPrice($data['original_price']);

      if($data['discount'] == null || $data['discount'] == "undefined"){
        $data['discount'] = "0";
      }
  
      $loja_id = Store::getStoreID($_SESSION['user_id']);  
      $data['store_id'] = $loja_id;      
      $nome_produto = str_ireplace(" ", "", $data['name']);  
      $data['unique_id'] = uniqid('PROD-'.$nome_produto);
      
      $inseriu = Product::saveProduct($data);

      if(!$inseriu){
        $this->return->setFailed("Erro ao criar o produto.");
        return;
      }
      else{

        $diretorio = realpath(storage_path() . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "..") . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR;
        $destino = $diretorio . "site" . DIRECTORY_SEPARATOR . "products" . DIRECTORY_SEPARATOR . "users" . DIRECTORY_SEPARATOR;
        
        $nomeHash =  md5($image->getClientOriginalName()) . '.' . $image->getClientOriginalExtension();
      
        if(!$image->move($destino, $nomeHash)){
          $this->return->setFailed("Ocorreu um erro ao fazer o upload da sua foto.");
          return;
        }
        else{
          $inserir = Image::salvarImagemProduto($nomeHash, $inseriu, 'profile');
          if(!$inserir){
            $this->return->setFailed("Ocorreu um erro ao armazenar sua imagem.");
            return;
          }
        }
      }

    }
    else{
      $this->return->setFailed(self::NO_IMAGE);
      return;
    }
  }

  // Alterar produto
  public function updateProduct(){
    $data = $this->get_post();

    if(isset($data['profile_image'])){
      unset($data['profile_image']);
    }
    if(isset($data['imagens'])){
      unset($data['imagens']);
    }

    $alterar = Product::updateProduct($data);

    if(!$alterar){
      $this->return->setFailed("Ocorreu um erro ao alterar o produto.");
    }

  }

  // Alterna o status do produto de ativado para desativado e vice versa
  public function toggleStatus(){
    $data = $this->get_post();

    $status = Product::toggleProductStatus($data);

    if(!$status){
      $this->return->setFailed("Ocorreu um erro e não foi possível alterar o status do produto.");
      return;
    }
  }

  // Pegar Produto X url/produtos/uniqueid
  public function getProduct(){
    $data = $this->get_post();
    $produto = Product::getViewableProduct($data['unique_id']);
    if($produto != null){
      $this->return->setObject($produto);
    }
    else{
      $this->return->setFailed("Não existe nenhum produto com esse identificador.");
      return;
    }
  }

  // Pega o produto para edição na tela de alterar produto
  public function getProductForEdition(){
    $data = $this->get_post();
    $produto = Product::getEditableProduct($data['unique_id'], $_SESSION['user_id']);

    if($produto != null){
      $this->return->setObject($produto);
    }
    else{
      $this->return->setFailed("Não existe nenhum produto com esse identificador.");
      return;
    }

  }

  // Pega os produtos de acordo com os filtros determinados
  public function getProducts(){
    $data = $this->get_post();
    $conditions = array();

    // OK
    if($data['category'] != 0){
      $conditions[] = ['category_id', '=', $data['category']];
    }

    // OK
    if(isset($data['filter'])){
      if($data['filter'] != 'unisex'){
        $conditions[] = ['gender', '=', $data['filter']];
      }      
    }
    
    if(isset($data['page'])){
      $page = $data['page'] - 1;
    }
    else{
      $page = 0;
    }

    // OK
    if(isset($data['quality'])){
      $quality = $data['quality'];
      if($quality != 'wherever'){
        $conditions[] = ['quality', '=', $quality];
      }      
    }

    $products = Product::getProducts($conditions, $page);

    if($products != null){
      $this->return->setObject($products);
      return;
    }
    else{
      return;
    }
  }

  // Indica a quantidade de páginas existentes na listagem de produtos /produtos
  public function getPageCount(){
    $data = $this->get_post();
    $condicoes = array();
    $condicoes[] = ['status', '=', 'ativado'];

    $categoria = $data['category'];
    if(isset($categoria) && strlen($categoria) > 0){
      $condicoes[] = ['category_id', '=', $data['category']];
    }

    $filtro = $data['filter'];    
    if(isset($filtro) && strlen($filtro) > 0){
      if($filtro != 'unisex'){
        $condicoes[]  = ['gender', '=', $filtro];
      }      
    }

    $qualidade = $data['quality'];
    if(isset($qualidade) && strlen($qualidade) > 0){
      if($qualidade != 'wherever'){
        $condicoes[] = ['quality', '=', $qualidade];
      }      
    }

    $quantidade = Product::quantityOfFilteredProducts($condicoes);

    $this->return->setObject($quantidade);
  }

  // Verifica se o usuário tem loja criada
  public function checkStore(){
    $loja = DB::table('stores')
    ->select('id')
    ->where('owner_id', '=', $_SESSION['user_id'])
    ->get();

    if(count($loja) > 0){
      return true;
    }else{
      return false;
    }
  }

  // Pega os produtos de determinada loja
  public function getProductFromStore(){
    $data = $this->get_post();

    $produtos = DB::table('stores')
    ->join('products', 'products.store_id', '=', 'stores.id')
    ->join('product_images', 'product_images.product_id', '=', 'products.id')
    ->select('products.unique_id', 'products.name', 'products.quality', 'products.price', 'products.gender', 'product_images.filename as imagem')
    ->where('stores.unique_id', '=', $data['unique_id'])
    ->where('product_images.type', 'profile')
    ->get();

    if(count($produtos) <= 0){
      $this->return->setFailed("Esta loja não possui produtos ainda.");
      return;
    }else{
      $this->return->setObject($produtos);
    }
  }

  // Converte String para double(preço)
  private function transformPrice($price){
    $price = explode(',', $price);
    $inteiro = (double) 0;
    $decimal = (double) 0;

    if(isset($price[0])){
      $inteiro = (double) $price[0];
    }
    if(isset($price[1])){
      $decimal = (double) $price[1];
      $decimal /= 100;
    }

    $valor = $inteiro + $decimal;
    return $valor;
  }

}
