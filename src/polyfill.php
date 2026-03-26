<?php

namespace Recca0120\Terminal
{
    function escapeshellarg($argument)
    {
        return ProcessUtils::escapeArgument($argument);
    }
}

namespace Symfony\Component\Process
{
    function escapeshellarg($input)
    {
        return \Recca0120\Terminal\escapeshellarg($input);
    }
}
