<?php

namespace MyApp;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Socket implements MessageComponentInterface
{

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    public function uniqidReal($lenght = 13)
    {
        if (function_exists("random_bytes")) {
            $bytes = random_bytes(ceil($lenght / 2));
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
        } else {
            throw new Exception("no cryptographically secure random function available");
        }
        return substr(bin2hex($bytes), 0, $lenght);
    }

    public function selectSpace()
    {
    }

    public function onOpen(ConnectionInterface $connw)
    {

        // Store the new connection in $this->clients
        $this->clients->attach($connw);

        echo "New connection! ({$connw->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $servername = "45.144.164.52";
        $username = "root";
        $password = "Rung_tom003";
        $db = "reserve_space";
        $port = "13306";
        // Create connection
        $conn = mysqli_connect($servername, $username, $password, $db, $port);
        $connect_status = "";
        $connect_message = "";
        // Check connection
        if (!$conn) {
            $connect_status = "failed";
            $connect_message = "Connection failed: " . mysqli_connect_error();
        } else {
            $connect_status = "success";
            $connect_message = "Connection success";
        }

        $resp = new Resp();
        $dataarea = array();

        if ($connect_status == "success") {
            $uuid_order = $this->uniqidReal();
            $obj = (array)json_decode($msg);

            $z_Id = $obj["z_Id"];
            $a_Id = $obj["a_Id"];
            $area_static = $obj["area_static"];
            $u_Id = $obj["u_Id"];
            $a_Name = $obj["a_Name"];
            $ActionStatus = $obj["ActionStatus"];

            if ($ActionStatus === "START") {

                $resp_start = new Resp();
                $dataarea_start = array();

                $sql_select_space = "SELECT b.z_Id,a.a_Id,b.z_Name,a.a_Name,a.a_ReserveStatus FROM reserve_space.tb_area as a inner join reserve_space.tb_zone as b on a.z_Id = b.z_Id where b.z_Id = '" . $z_Id . "' and a.a_Name LIKE '%" . $a_Name . "%';";
                $result_select_space = $conn->query($sql_select_space);
                if ($result_select_space->num_rows > 0) {
                    while ($row = $result_select_space->fetch_assoc()) {
                        array_push($dataarea_start, $row);
                    }
                    $resp_start->data = $dataarea_start;
                    $resp_start->set_status("seccess");
                } else {
                    $resp_start->set_status("fail");
                }

                foreach ($this->clients as $client) {

                    if ($from->resourceId == $client->resourceId) {
                        $client->send(json_encode($resp_start));
                        break;
                    }
                    //$client->send(json_encode($resp_start));
                }
            } else {
                $sql_select_area = "SELECT * FROM reserve_space.tb_area where (a_ReserveStatus = '1' OR a_ReserveStatus = '4') AND a_Id = '" . $a_Id . "'";
                $result_select_area = $conn->query($sql_select_area);
                if ($result_select_area->num_rows > 0) {
                    //ถ้ามีจองอยู่เเล้ว
                    $resp->set_message("มีการจองพื้นที่ไปแล้ว");
                    $resp->set_status("fail");
                } else {
                    if ($z_Id == "2dacd150-9b8b-11ed-8054-0242ac110004" && $area_static == "0") //โซนอาหาร
                    {
                        $sql_select_reserve = "SELECT * FROM reserve_space.tb_reserve as a  INNER JOIN reserve_space.tb_area as b ON a.a_Id = b.a_Id INNER JOIN reserve_space.tb_zone as c ON b.z_Id = c.z_Id WHERE b.z_Id = '" . $z_Id . "' AND a.r_Status = '1';";
                        $result = $conn->query($sql_select_reserve);
                        if ($result->num_rows > 0) {
                            $resp->set_message("ไม่สามารถจองพื้นที่เพิ่มได้เนื่องจาก 1 คน ต่อ 1 ล็อค ในโซนอาหาร");
                            $resp->set_status("fail");
                        } else {
                            $sql_insert_TBreserve = "INSERT INTO `reserve_space`.`tb_reserve` (`r_Id`, `u_Id`, `a_Id`, `r_Status`) VALUES ('" . $uuid_order . "', '" . $u_Id . "', '" . $a_Id . "', '1');";
                            $sql_insert_TBreserve .= "UPDATE `reserve_space`.`tb_area` SET `a_ReserveStatus` = '1' WHERE (`a_Id` = '" . $a_Id . "');";
                            //สถานะ 1 จองสำเร็จ
                            if ($conn->multi_query($sql_insert_TBreserve) === TRUE) {
                                $conn->close();
                                // Create connection
                                $conn = mysqli_connect($servername, $username, $password, $db, $port);
                                // Check connection
                                if (!$conn) {
                                    $connect_status = "failed";
                                    $connect_message = "Connection failed: " . mysqli_connect_error();

                                    $resp->set_message("มีข้อผิดพลาดเกิดขึ้น." . $connect_message);
                                    $resp->set_status("fail");
                                } else {
                                    $sql_select_space = "SELECT b.z_Id,a.a_Id,b.z_Name,a.a_Name,a.a_ReserveStatus FROM reserve_space.tb_area as a inner join reserve_space.tb_zone as b on a.z_Id = b.z_Id where b.z_Id = '" . $z_Id . "' and a.a_Name LIKE '%" . $a_Name . "%';";

                                    $result_select_space = $conn->query($sql_select_space);
                                    if ($result_select_space->num_rows > 0) {
                                        while ($row = $result_select_space->fetch_assoc()) {
                                            array_push($dataarea, $row);
                                        }

                                        $resp->data = $dataarea;
                                        $resp->set_status("seccess");
                                        $resp->set_message("จองพื้นที่สำเร็จ.");
                                    } else {
                                        $resp->set_message("มีข้อผิดพลาดเกิดขึ้น.");
                                        $resp->set_status("fail");
                                    }
                                }
                            } else {
                                $resp->set_message("มีข้อผิดพลาดเกิดขึ้น.");
                                $resp->set_status("fail");
                            }
                        }
                    } else {
                        if ($area_static == "1") {
                            //ถ้าเป็นล็อคประจำ
                            $sql_select_static = "SELECT * FROM reserve_space.tb_reserve where u_Id = '" . $u_Id . "' AND a_Id = '" . $a_Id . "' AND r_Status = '2';";
                            $result_select_static = $conn->query($sql_select_static);
                            if ($result_select_static->num_rows > 0) {
                                //ถ้า user มีล็อคประจำอยู่เเล้ว
                                $resp->set_message("คุณมีล็อคนี้เป็นล็อคประจำอยู่เเล้วโปรดแจ้งเจ้าหน้าที่");
                                $resp->set_status("fail");
                            } else {
                                $sql_insert_TBreserve = "INSERT INTO `reserve_space`.`tb_reserve` (`r_Id`, `u_Id`, `a_Id`, `r_Status`) VALUES ('" . $uuid_order . "', '" . $u_Id . "', '" . $a_Id . "', '1');";
                                $sql_insert_TBreserve .= "UPDATE `reserve_space`.`tb_area` SET `a_ReserveStatus` = '4' WHERE (`a_Id` = '" . $a_Id . "');";
                                //สถานะ 3 จองล็อคประจำที่ว่างสำเร็จ
                                if ($conn->multi_query($sql_insert_TBreserve) === TRUE) {

                                    $conn->close();
                                    // Create connection
                                    $conn = mysqli_connect($servername, $username, $password, $db, $port);
                                    // Check connection
                                    if (!$conn) {
                                        $connect_status = "failed";
                                        $connect_message = "Connection failed: " . mysqli_connect_error();

                                        $resp->set_message("มีข้อผิดพลาดเกิดขึ้น." . $connect_message);
                                        $resp->set_status("fail");
                                    } else {
                                        $sql_select_space = "SELECT b.z_Id,a.a_Id,b.z_Name,a.a_Name,a.a_ReserveStatus FROM reserve_space.tb_area as a inner join reserve_space.tb_zone as b on a.z_Id = b.z_Id where b.z_Id = '" . $z_Id . "' and a.a_Name LIKE '%" . $a_Name . "%';";

                                        $result_select_space = $conn->query($sql_select_space);
                                        if ($result_select_space->num_rows > 0) {
                                            while ($row = $result_select_space->fetch_assoc()) {
                                                array_push($dataarea, $row);
                                            }

                                            $resp->data = $dataarea;
                                            $resp->set_status("seccess");
                                            $resp->set_message("จองพื้นที่สำเร็จ.");
                                        } else {
                                            $resp->set_message("มีข้อผิดพลาดเกิดขึ้น.");
                                            $resp->set_status("fail");
                                        }
                                    }
                                } else {
                                    $resp->set_message("มีข้อผิดพลาดเกิดขึ้น.");
                                    $resp->set_status("fail");
                                }
                            }
                        } else {
                            $sql_insert_TBreserve = "INSERT INTO `reserve_space`.`tb_reserve` (`r_Id`, `u_Id`, `a_Id`, `r_Status`) VALUES ('" . $uuid_order . "', '" . $u_Id . "', '" . $a_Id . "', '1');";
                            $sql_insert_TBreserve .= "UPDATE `reserve_space`.`tb_area` SET `a_ReserveStatus` = '1' WHERE (`a_Id` = '" . $a_Id . "');";
                            //สถานะ 1 จองสำเร็จ
                            if ($conn->multi_query($sql_insert_TBreserve) === TRUE) {

                                $conn->close();
                                // Create connection
                                $conn = mysqli_connect($servername, $username, $password, $db, $port);
                                // Check connection
                                if (!$conn) {
                                    $connect_status = "failed";
                                    $connect_message = "Connection failed: " . mysqli_connect_error();

                                    $resp->set_message("มีข้อผิดพลาดเกิดขึ้น." . $connect_message);
                                    $resp->set_status("fail");
                                } else {
                                    $sql_select_space = "SELECT b.z_Id,a.a_Id,b.z_Name,a.a_Name,a.a_ReserveStatus FROM reserve_space.tb_area as a inner join reserve_space.tb_zone as b on a.z_Id = b.z_Id where b.z_Id = '" . $z_Id . "' and a.a_Name LIKE '%" . $a_Name . "%';";

                                    $result_select_space = $conn->query($sql_select_space);
                                    if ($result_select_space->num_rows > 0) {
                                        while ($row = $result_select_space->fetch_assoc()) {
                                            array_push($dataarea, $row);
                                        }

                                        $resp->data = $dataarea;
                                        $resp->set_status("seccess");
                                        $resp->set_message("จองพื้นที่สำเร็จ.");
                                    } else {
                                        $resp->set_message("มีข้อผิดพลาดเกิดขึ้น.");
                                        $resp->set_status("fail");
                                    }
                                }
                            } else {
                                $resp->set_message("มีข้อผิดพลาดเกิดขึ้น.");
                                $resp->set_status("fail");
                            }
                        }
                    }
                }

                foreach ($this->clients as $client) {
                    if ($from->resourceId == $client->resourceId) {
                        continue;
                    }
                    $client->send(json_encode($resp));
                }
            }
        } else {
            $resp->set_message("connection database fail.");
            $resp->set_status("fail");

            foreach ($this->clients as $client) {

                if ($from->resourceId == $client->resourceId) {
                    $client->send(json_encode($resp_start));
                    break;
                }
            }
        }
        $conn->close();
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }
}
