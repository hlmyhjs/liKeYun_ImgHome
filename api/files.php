<?php
require_once 'config.php';

// 连接到数据库
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// 检查连接
if ($conn->connect_error) {
  die("数据库连接失败: " . $conn->connect_error);
}

// 设置响应头
header('Content-Type: application/json');

// 获取分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = isset($_GET['pageSize']) ? max(1, intval($_GET['pageSize'])) : 10;

// 获取上传历史记录
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (!isset($_GET['name'])) {
    // 返回分页结果
    $response = getUploadHistory($conn, $page, $pageSize);
    echo json_encode($response);
  } else {
    // 检查图片是否存在
    $imageName = $_GET['name'];
    $response = checkImageExists($conn, $imageName);
    echo json_encode($response);
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 保存上传历史记录
  $uploadData = json_decode(file_get_contents('php://input'), true);
  saveUploadHistory($conn, $uploadData);
}

// 关闭数据库连接
$conn->close();

// 获取上传历史记录
function getUploadHistory($conn, $page, $pageSize)
{
  $uploadHistoryTable = UPLOAD_HISTORY_TABLE;
  $startIndex = ($page - 1) * $pageSize;
  $sql = "SELECT COUNT(*) AS total FROM $uploadHistoryTable";
  $result = $conn->query($sql);
  $row = $result->fetch_assoc();
  $total = (int)$row['total'];
  $totalPages = ceil($total / $pageSize);
  $sql = "SELECT * FROM $uploadHistoryTable ORDER BY time DESC LIMIT $startIndex, $pageSize";
  $result = $conn->query($sql);
  $currentPageRecords = [];
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $currentPageRecords[] = $row;
    }
  }
  return [
    'code' => 200,
    'total' => $total,
    'totalPages' => $totalPages,
    'currentPage' => $page,
    'pageSize' => $pageSize,
    'list' => $currentPageRecords
  ];
}

// 检查图片是否存在
function checkImageExists($conn, $imageName)
{
  $uploadHistoryTable = UPLOAD_HISTORY_TABLE;
  $imageName = $conn->real_escape_string($imageName);
  $sql = "SELECT COUNT(*) AS count FROM $uploadHistoryTable WHERE name='$imageName'";
  $result = $conn->query($sql);
  $count = $result->fetch_assoc()["count"];
  if ($count > 0) {
    return [
      'code' => 201,
      'msg' => '图片已存在',
      'canUpload' => false
    ];
  } else {
    return [
      'code' => 200,
      'canUpload' => true
    ];
  }
}

// 保存上传历史记录
function saveUploadHistory($conn, $uploadData)
{
  $uploadHistoryTable = UPLOAD_HISTORY_TABLE;
  if ($uploadData === null || !isset($uploadData['url']) || !isset($uploadData['name'])) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'error' => 'Invalid data']);
    exit;
  }
  $uploadedFileName = basename($uploadData['name']);
  $imageName = $conn->real_escape_string($uploadedFileName);
  $response = checkImageExists($conn, $uploadedFileName);
  if ($response['canUpload']) {
    $uploadUrl = $conn->real_escape_string($uploadData['url']);
    $sql = "INSERT INTO $uploadHistoryTable (url, name, time) VALUES ('$uploadUrl', '$imageName', NOW())";
    if ($conn->query($sql) === false) {
      http_response_code(500);
      echo json_encode([
        'code' => 500,
        'msg' => '上传出错, 请稍后再试'
      ]);
      exit;
    }
    echo json_encode([
      'code' => 200,
      'msg' => '上传成功'
    ]);
  } else {
    echo json_encode($response);
  }
}
