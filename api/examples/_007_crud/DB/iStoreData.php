<?php

interface iStoreData
{
    function get($id);

    function getAll();

    function insert($rec);

    function update($id, $rec);

    function delete($id);
}